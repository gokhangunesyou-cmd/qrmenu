<?php

namespace App\Controller\Site;

use App\Entity\CustomerAccount;
use App\Entity\CustomerSubscription;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Repository\PlanRepository;
use App\Repository\RoleRepository;
use App\Repository\SiteContentRepository;
use App\Repository\ThemeRepository;
use App\Service\LanguageContext;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

class SignupController extends AbstractController
{
    #[Route('/signup', name: 'site_signup', methods: ['GET', 'POST'])]
    public function signup(
        Request $request,
        PlanRepository $planRepository,
        RoleRepository $roleRepository,
        ThemeRepository $themeRepository,
        SiteContentRepository $siteContentRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        LanguageContext $languageContext,
        TranslationService $translationService,
    ): Response {
        $plans = $planRepository->findActiveOrdered();
        $locale = $languageContext->resolveSiteLocale($request, 'tr');
        $cms = $this->buildSiteCms($siteContentRepository, $translationService, $locale);
        if ($request->isMethod('GET')) {
            return $this->render('site/signup.html.twig', [
                'plans' => $plans,
                'cms' => $cms,
                'currentLocale' => $locale,
                'availableLocales' => $languageContext->getLocaleLabelMap(),
            ]);
        }

        if (!$this->isCsrfTokenValid('site_signup', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $planCode = trim($request->request->getString('plan_code'));
        $plan = $planRepository->findOneBy(['code' => $planCode, 'isActive' => true]);
        if ($plan === null) {
            $this->addFlash('error', 'Gecerli bir plan seciniz.');
            return $this->render('site/signup.html.twig', [
                'plans' => $plans,
                'cms' => $cms,
                'currentLocale' => $locale,
                'availableLocales' => $languageContext->getLocaleLabelMap(),
            ]);
        }

        $accountName = trim($request->request->getString('account_name'));
        $email = trim($request->request->getString('email'));
        $password = $request->request->getString('password');
        $restaurantName = trim($request->request->getString('restaurant_name'));

        if ($accountName === '' || $email === '' || $password === '' || $restaurantName === '') {
            $this->addFlash('error', 'Tum zorunlu alanlari doldurunuz.');
            return $this->render('site/signup.html.twig', [
                'plans' => $plans,
                'cms' => $cms,
                'currentLocale' => $locale,
                'availableLocales' => $languageContext->getLocaleLabelMap(),
            ]);
        }

        if ($em->getRepository(User::class)->findOneBy(['email' => $email]) !== null) {
            $this->addFlash('error', 'Bu e-posta ile kayitli kullanici var.');
            return $this->render('site/signup.html.twig', [
                'plans' => $plans,
                'cms' => $cms,
                'currentLocale' => $locale,
                'availableLocales' => $languageContext->getLocaleLabelMap(),
            ]);
        }

        $theme = $themeRepository->findAllActive()[0] ?? null;
        if ($theme === null) {
            throw $this->createNotFoundException('Aktif tema bulunamadi.');
        }

        $account = new CustomerAccount($accountName);
        $account->setEmail($email);

        $restaurant = new Restaurant($restaurantName, $this->buildUniqueRestaurantSlug($restaurantName, $em), $theme);
        $restaurant->setIsActive(true);
        $restaurant->setEmail($email);
        $restaurant->setCustomerAccount($account);

        $ownerRole = $roleRepository->findOneBy(['name' => 'ROLE_RESTAURANT_OWNER']);
        if ($ownerRole === null) {
            throw $this->createNotFoundException('ROLE_RESTAURANT_OWNER role not found.');
        }

        $user = new User($email, '', trim($request->request->getString('first_name', 'Isletme')), trim($request->request->getString('last_name', 'Yoneticisi')));
        $user->setPasswordHash($hasher->hashPassword($user, $password));
        $user->addRole($ownerRole);
        $user->setRestaurant($restaurant);
        $user->addRestaurant($restaurant);
        $user->setCustomerAccount($account);

        $start = new \DateTimeImmutable('today');
        $end = $start->modify('+1 year');
        $subscription = new CustomerSubscription($account, $plan, $start, $end);

        $em->persist($account);
        $em->persist($restaurant);
        $em->persist($user);
        $em->persist($subscription);
        $em->flush();

        $this->addFlash('success', 'Uyelik olusturuldu. Simdi admin girisi yapabilirsiniz.');

        return $this->redirectToRoute('admin_login');
    }

    private function buildUniqueRestaurantSlug(string $name, EntityManagerInterface $em): string
    {
        $slugger = new AsciiSlugger();
        $base = strtolower((string) $slugger->slug($name));
        $base = $base !== '' ? $base : 'restaurant';

        $attempt = $base;
        $i = 1;
        while ($em->getRepository(Restaurant::class)->findOneBy(['slug' => $attempt]) !== null) {
            $attempt = sprintf('%s-%d', $base, $i);
            ++$i;
        }

        return $attempt;
    }

    /**
     * @return array<string, string>
     */
    private function buildSiteCms(SiteContentRepository $siteContentRepository, TranslationService $translationService, string $locale): array
    {
        $publishedHomeContents = $siteContentRepository->findPublishedByPrefix('home_');
        $contentMap = [];
        $contentIds = array_values(array_map(static fn ($content): int => (int) $content->getId(), $publishedHomeContents));
        $translations = $translationService->getFieldMapWithFallback('site_content', $contentIds, $locale);

        foreach ($publishedHomeContents as $content) {
            if (str_starts_with($content->getKeyName(), 'home_slider_')) {
                continue;
            }
            $contentMap[$content->getKeyName()] = $translationService->resolve($translations, (int) $content->getId(), 'body', $content->getBody()) ?? $content->getBody();
        }

        return [
            'headerBrand' => $this->resolveContentValue($contentMap, 'home_header_brand', 'Altay QR Menu'),
            'headerCtaText' => $this->resolveContentValue($contentMap, 'home_header_cta_text', 'Panel Girisi'),
            'headerCtaUrl' => $this->resolveContentValue($contentMap, 'home_header_cta_url', $this->generateUrl('admin_login')),
            'contactTitle' => $this->resolveContentValue($contentMap, 'home_contact_title', 'Satis ve Destek'),
            'contactNote' => $this->resolveContentValue($contentMap, 'home_contact_note', 'Satis demosu, fiyatlandirma ve onboarding sureci icin bize ulasin.'),
            'contactEmail' => $this->resolveContentValue($contentMap, 'home_contact_email', 'iletisim@altayqrmenu.com'),
            'contactPhone' => $this->resolveContentValue($contentMap, 'home_contact_phone', '+90 850 000 00 00'),
            'footerText' => $this->resolveContentValue($contentMap, 'home_footer_text', 'Altay QR Menu - tum haklari saklidir.'),
        ];
    }

    /**
     * @param array<string, string> $contentMap
     */
    private function resolveContentValue(array $contentMap, string $key, string $fallback): string
    {
        if (!isset($contentMap[$key])) {
            return $fallback;
        }

        $value = trim($contentMap[$key]);

        return $value !== '' ? $value : $fallback;
    }
}
