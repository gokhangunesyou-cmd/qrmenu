<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CustomerAccount;
use App\Entity\CustomerSubscription;
use App\Entity\Plan;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Repository\CustomerSubscriptionRepository;
use App\Repository\PlanRepository;
use App\Repository\RestaurantRepository;
use App\Repository\RoleRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserRepository;
use App\Service\PlanPeriod;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/account')]
class AccountWebController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustomerSubscriptionRepository $subscriptionRepository,
        private readonly PlanRepository $planRepository,
        private readonly UserRepository $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly ThemeRepository $themeRepository,
        private readonly RestaurantRepository $restaurantRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'admin_account_index', methods: ['GET'])]
    public function index(): Response
    {
        $owner = $this->getAuthenticatedOwner();
        $account = $this->getAccountOrFail($owner);
        if (($renewRedirect = $this->ensureActiveSubscriptionOrRedirect($account)) instanceof RedirectResponse) {
            return $renewRedirect;
        }

        $subscription = $this->subscriptionRepository->findActiveForAccount($account);
        $plan = $subscription?->getPlan();

        $restaurants = $this->restaurantRepository->findBy(['customerAccount' => $account], ['name' => 'ASC']);
        $users = $this->userRepository->findBy(['customerAccount' => $account], ['createdAt' => 'ASC']);

        return $this->render('admin/account/index.html.twig', [
            'account' => $account,
            'subscription' => $subscription,
            'currentPlan' => $plan,
            'plans' => $this->planRepository->findActiveOrdered(),
            'restaurants' => $restaurants,
            'users' => $users,
            'restaurantsUsed' => count($restaurants),
            'usersUsed' => count($users),
        ]);
    }

    #[Route('/subscription/renew', name: 'admin_account_subscription_renew', methods: ['GET'])]
    public function renewView(): Response
    {
        $owner = $this->getAuthenticatedOwner();
        $account = $this->getAccountOrFail($owner);
        $subscriptionExpired = $this->suspendExpiredSubscriptionIfNeeded($account);
        $activeSubscription = $this->subscriptionRepository->findActiveForAccount($account);

        if ($activeSubscription instanceof CustomerSubscription) {
            return $this->redirectToRoute('admin_account_index');
        }

        if ($subscriptionExpired) {
            $this->addFlash('error', 'Abonelik sureniz doldugu icin hesap askiya alindi. Devam etmek icin yenileyin.');
        }

        return $this->render('admin/account/renew.html.twig', [
            'plans' => $this->planRepository->findActiveOrdered(),
            'latestSubscription' => $this->subscriptionRepository->findLatestForAccount($account),
        ]);
    }

    #[Route('/subscription/renew', name: 'admin_account_subscription_renew_submit', methods: ['POST'])]
    public function renewSubmit(Request $request): RedirectResponse
    {
        $this->validateCsrfOrFail($request, 'account_subscription_renew');

        $owner = $this->getAuthenticatedOwner();
        $account = $this->getAccountOrFail($owner);
        $this->suspendExpiredSubscriptionIfNeeded($account);

        $planId = $request->request->getInt('plan_id');
        $plan = $this->planRepository->find($planId);
        if ($plan === null || !$plan->isActive()) {
            $this->addFlash('error', 'Gecerli bir plan seciniz.');
            return $this->redirectToRoute('admin_account_subscription_renew');
        }

        $restaurantsUsed = count($this->restaurantRepository->findBy(['customerAccount' => $account]));
        $usersUsed = count($this->userRepository->findBy(['customerAccount' => $account]));
        if ($restaurantsUsed > $plan->getMaxRestaurants() || $usersUsed > $plan->getMaxUsers()) {
            $this->addFlash('error', 'Bu plana gecis icin mevcut restoran/kullanici sayinizi azaltmaniz gerekiyor.');
            return $this->redirectToRoute('admin_account_subscription_renew');
        }

        $today = new \DateTimeImmutable('today');
        $activeSubscription = $this->subscriptionRepository->findActiveForAccount($account, $today);

        if ($activeSubscription instanceof CustomerSubscription && $activeSubscription->getPlan()->getId() === $plan->getId()) {
            $activeSubscription->setEndsAt(PlanPeriod::addDuration($activeSubscription->getEndsAt(), $plan));
            $activeSubscription->setIsActive(true);
            $this->em->flush();

            $this->addFlash('success', sprintf('Aboneliginiz %s sure ile uzatildi. Yeni bitis: %s', $this->planDurationText($plan), $activeSubscription->getEndsAt()->format('d.m.Y')));

            return $this->redirectToRoute('admin_account_index');
        }

        if ($activeSubscription instanceof CustomerSubscription) {
            $activeSubscription->setIsActive(false);
        }

        $subscription = new CustomerSubscription($account, $plan, $today, PlanPeriod::addDuration($today, $plan));
        $this->em->persist($subscription);
        $this->em->flush();

        $this->addFlash('success', sprintf('Aboneliginiz %s plani ile %s sureli olarak baslatildi.', $plan->getName(), $this->planDurationText($plan)));

        return $this->redirectToRoute('admin_account_index');
    }

    #[Route('/membership/update', name: 'admin_account_membership_update', methods: ['POST'])]
    public function updateMembership(Request $request): RedirectResponse
    {
        $this->validateCsrfOrFail($request, 'account_membership_update');

        $owner = $this->getAuthenticatedOwner();
        $account = $this->getAccountOrFail($owner);

        $name = trim($request->request->getString('name'));
        if ($name === '') {
            $this->addFlash('error', 'Uyelik/isletme adi bos olamaz.');
            return $this->redirectToRoute('admin_account_index');
        }

        $account->setName($name);
        $account->setEmail(trim($request->request->getString('email')) ?: null);
        $this->em->flush();

        $this->addFlash('success', 'Uyelik bilgileri guncellendi.');

        return $this->redirectToRoute('admin_account_index');
    }

    #[Route('/plan/change', name: 'admin_account_plan_change', methods: ['POST'])]
    public function changePlan(Request $request): RedirectResponse
    {
        $this->validateCsrfOrFail($request, 'account_plan_change');

        $owner = $this->getAuthenticatedOwner();
        $account = $this->getAccountOrFail($owner);
        if (($renewRedirect = $this->ensureActiveSubscriptionOrRedirect($account)) instanceof RedirectResponse) {
            return $renewRedirect;
        }

        $planId = $request->request->getInt('plan_id');
        $plan = $this->planRepository->find($planId);
        if ($plan === null || !$plan->isActive()) {
            $this->addFlash('error', 'Gecerli bir plan seciniz.');
            return $this->redirectToRoute('admin_account_index');
        }

        $restaurantsUsed = count($this->restaurantRepository->findBy(['customerAccount' => $account]));
        $usersUsed = count($this->userRepository->findBy(['customerAccount' => $account]));
        if ($restaurantsUsed > $plan->getMaxRestaurants() || $usersUsed > $plan->getMaxUsers()) {
            $this->addFlash('error', 'Bu plana gecis icin mevcut restoran/kullanici sayinizi azaltmaniz gerekiyor.');
            return $this->redirectToRoute('admin_account_index');
        }

        $subscription = $this->subscriptionRepository->findActiveForAccount($account);
        if (!$subscription instanceof CustomerSubscription) {
            $today = new \DateTimeImmutable('today');
            $subscription = new CustomerSubscription($account, $plan, $today, PlanPeriod::addDuration($today, $plan));
            $this->em->persist($subscription);
        } else {
            $today = new \DateTimeImmutable('today');
            $subscription->setPlan($plan);
            $subscription->setStartsAt($today);
            $subscription->setEndsAt(PlanPeriod::addDuration($today, $plan));
            $subscription->setIsActive(true);
        }

        $this->em->flush();

        $this->addFlash('success', 'Plan basariyla degistirildi.');

        return $this->redirectToRoute('admin_account_index');
    }

    #[Route('/restaurants/create', name: 'admin_account_restaurant_create', methods: ['POST'])]
    public function createRestaurant(Request $request): RedirectResponse
    {
        $this->validateCsrfOrFail($request, 'account_restaurant_create');

        $owner = $this->getAuthenticatedOwner();
        $account = $this->getAccountOrFail($owner);
        if (($renewRedirect = $this->ensureActiveSubscriptionOrRedirect($account)) instanceof RedirectResponse) {
            return $renewRedirect;
        }

        $subscription = $this->subscriptionRepository->findActiveForAccount($account);
        $plan = $subscription?->getPlan();

        if ($plan === null) {
            $this->addFlash('error', 'Aktif bir abonelik olmadan restoran acamazsiniz.');
            return $this->redirectToRoute('admin_account_index');
        }

        $restaurantCount = count($this->restaurantRepository->findBy(['customerAccount' => $account]));
        if ($restaurantCount >= $plan->getMaxRestaurants()) {
            $this->addFlash('error', sprintf('Plan limitine ulastiniz. En fazla %d restoran acabilirsiniz.', $plan->getMaxRestaurants()));
            return $this->redirectToRoute('admin_account_index');
        }

        $name = trim($request->request->getString('name'));
        if ($name === '') {
            $this->addFlash('error', 'Restoran adi zorunludur.');
            return $this->redirectToRoute('admin_account_index');
        }

        $theme = $this->themeRepository->findAllActive()[0] ?? null;
        if ($theme === null) {
            throw $this->createNotFoundException('Aktif tema bulunamadi.');
        }

        $restaurant = new Restaurant($name, $this->buildUniqueRestaurantSlug($name), $theme);
        $restaurant->setIsActive(true);
        $restaurant->setEmail(trim($request->request->getString('email')) ?: null);
        $restaurant->setCustomerAccount($account);

        $owner->addRestaurant($restaurant);
        if ($owner->getRestaurant() === null) {
            $owner->setRestaurant($restaurant);
        }

        $this->em->persist($restaurant);
        $this->em->flush();

        $this->addFlash('success', 'Yeni restoran olusturuldu.');

        return $this->redirectToRoute('admin_account_index');
    }

    #[Route('/users/create', name: 'admin_account_user_create', methods: ['POST'])]
    public function createUser(Request $request): RedirectResponse
    {
        $this->validateCsrfOrFail($request, 'account_user_create');

        $owner = $this->getAuthenticatedOwner();
        $account = $this->getAccountOrFail($owner);
        if (($renewRedirect = $this->ensureActiveSubscriptionOrRedirect($account)) instanceof RedirectResponse) {
            return $renewRedirect;
        }

        $subscription = $this->subscriptionRepository->findActiveForAccount($account);
        $plan = $subscription?->getPlan();

        if ($plan === null) {
            $this->addFlash('error', 'Aktif bir abonelik olmadan kullanici olusturamazsiniz.');
            return $this->redirectToRoute('admin_account_index');
        }

        $userCount = count($this->userRepository->findBy(['customerAccount' => $account]));
        if ($userCount >= $plan->getMaxUsers()) {
            $this->addFlash('error', sprintf('Plan limitine ulastiniz. En fazla %d kullanici olusturabilirsiniz.', $plan->getMaxUsers()));
            return $this->redirectToRoute('admin_account_index');
        }

        $email = trim($request->request->getString('email'));
        $firstName = trim($request->request->getString('first_name'));
        $lastName = trim($request->request->getString('last_name'));
        $password = $request->request->getString('password');

        if ($email === '' || $firstName === '' || $lastName === '' || $password === '') {
            $this->addFlash('error', 'Tum kullanici alanlari zorunludur.');
            return $this->redirectToRoute('admin_account_index');
        }

        if (strlen($password) < 8) {
            $this->addFlash('error', 'Sifre en az 8 karakter olmalidir.');
            return $this->redirectToRoute('admin_account_index');
        }

        if ($this->userRepository->findByEmail($email) !== null) {
            $this->addFlash('error', 'Bu e-posta ile kayitli kullanici var.');
            return $this->redirectToRoute('admin_account_index');
        }

        $selectedRestaurants = $this->resolveSelectedRestaurantsForAccount($request, $account);
        if ($selectedRestaurants === []) {
            $this->addFlash('error', 'Kullanici icin en az bir restoran secmelisiniz.');
            return $this->redirectToRoute('admin_account_index');
        }

        $ownerRole = $this->roleRepository->findOneBy(['name' => 'ROLE_RESTAURANT_OWNER']);
        if ($ownerRole === null) {
            throw $this->createNotFoundException('ROLE_RESTAURANT_OWNER role bulunamadi.');
        }

        $newUser = new User($email, '', $firstName, $lastName);
        $newUser->setPasswordHash($this->passwordHasher->hashPassword($newUser, $password));
        $newUser->setCustomerAccount($account);
        $newUser->addRole($ownerRole);

        foreach ($selectedRestaurants as $restaurant) {
            $newUser->addRestaurant($restaurant);
        }

        $newUser->setRestaurant($selectedRestaurants[0]);

        $this->em->persist($newUser);
        $this->em->flush();

        $this->addFlash('success', 'Yeni kullanici olusturuldu ve restoran yetkileri tanimlandi.');

        return $this->redirectToRoute('admin_account_index');
    }

    #[Route('/users/{id}/permissions', name: 'admin_account_user_permissions_update', methods: ['POST'])]
    public function updateUserPermissions(int $id, Request $request): RedirectResponse
    {
        $this->validateCsrfOrFail($request, 'account_user_permissions_' . $id);

        $owner = $this->getAuthenticatedOwner();
        $account = $this->getAccountOrFail($owner);
        if (($renewRedirect = $this->ensureActiveSubscriptionOrRedirect($account)) instanceof RedirectResponse) {
            return $renewRedirect;
        }

        $user = $this->userRepository->find($id);
        if (!$user instanceof User || $user->getCustomerAccount()?->getId() !== $account->getId()) {
            throw $this->createNotFoundException('Kullanici bulunamadi.');
        }

        $selectedRestaurants = $this->resolveSelectedRestaurantsForAccount($request, $account);
        if ($selectedRestaurants === []) {
            $this->addFlash('error', 'En az bir restoran secmelisiniz.');
            return $this->redirectToRoute('admin_account_index');
        }

        foreach ($user->getRestaurants()->toArray() as $restaurant) {
            $user->removeRestaurant($restaurant);
        }

        foreach ($selectedRestaurants as $restaurant) {
            $user->addRestaurant($restaurant);
        }

        $user->setRestaurant($selectedRestaurants[0]);
        $this->em->flush();

        $this->addFlash('success', sprintf('%s icin restoran yetkileri guncellendi.', $user->getEmail()));

        return $this->redirectToRoute('admin_account_index');
    }

    private function validateCsrfOrFail(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function getAuthenticatedOwner(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $this->denyAccessUnlessGranted('ROLE_RESTAURANT_OWNER');

        return $user;
    }

    private function getAccountOrFail(User $user): CustomerAccount
    {
        $account = $user->getCustomerAccount();
        if (!$account instanceof CustomerAccount) {
            throw $this->createNotFoundException('Customer account not found.');
        }

        return $account;
    }

    private function ensureActiveSubscriptionOrRedirect(CustomerAccount $account): ?RedirectResponse
    {
        $subscriptionExpired = $this->suspendExpiredSubscriptionIfNeeded($account);
        $subscription = $this->subscriptionRepository->findActiveForAccount($account);
        if ($subscription instanceof CustomerSubscription) {
            return null;
        }

        if ($subscriptionExpired) {
            $this->addFlash('error', 'Abonelik sureniz doldugu icin hesap askiya alindi. Devam etmek icin yenileyin.');
        } else {
            $this->addFlash('error', 'Aktif aboneliginiz yok. Devam etmek icin aboneligi yenileyin.');
        }

        return $this->redirectToRoute('admin_account_subscription_renew');
    }

    private function suspendExpiredSubscriptionIfNeeded(CustomerAccount $account): bool
    {
        $latestActive = $this->subscriptionRepository->findLatestActiveForAccount($account);
        if (!$latestActive instanceof CustomerSubscription) {
            return false;
        }

        $today = new \DateTimeImmutable('today');
        if ($latestActive->getEndsAt() >= $today) {
            return false;
        }

        $latestActive->setIsActive(false);
        $this->em->flush();

        return true;
    }

    /**
     * @return Restaurant[]
     */
    private function resolveSelectedRestaurantsForAccount(Request $request, CustomerAccount $account): array
    {
        $raw = $request->request->all()['restaurant_ids'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $selectedIds = array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)));
        if ($selectedIds === []) {
            return [];
        }

        $restaurants = $this->restaurantRepository->findBy(['customerAccount' => $account]);
        $byId = [];
        foreach ($restaurants as $restaurant) {
            if ($restaurant->getId() !== null) {
                $byId[$restaurant->getId()] = $restaurant;
            }
        }

        $selected = [];
        foreach ($selectedIds as $id) {
            if (isset($byId[$id])) {
                $selected[] = $byId[$id];
            }
        }

        return $selected;
    }

    private function buildUniqueRestaurantSlug(string $name): string
    {
        $slugger = new AsciiSlugger();
        $base = strtolower((string) $slugger->slug($name));
        $base = $base !== '' ? $base : 'restaurant';

        $attempt = $base;
        $i = 1;
        while (true) {
            $existing = $this->restaurantRepository->findBySlug($attempt);
            if ($existing === null) {
                return $attempt;
            }

            $attempt = sprintf('%s-%d', $base, $i);
            ++$i;
        }
    }

    private function planDurationText(Plan $plan): string
    {
        return PlanPeriod::durationMonthsForCode($plan->getCode()) === 3 ? '3 ay' : '1 yil';
    }
}
