<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\CustomerSubscription;
use App\Entity\CustomerAccount;
use App\Entity\User;
use App\Repository\CustomerSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SubscriptionGuardListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly CustomerSubscriptionRepository $subscriptionRepository,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/admin')) {
            return;
        }

        if (
            str_starts_with($path, '/admin/login')
            || str_starts_with($path, '/admin/logout')
            || str_starts_with($path, '/admin/account/subscription/renew')
        ) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (!in_array('ROLE_RESTAURANT_OWNER', $user->getRoles(), true)) {
            return;
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return;
        }

        $account = $user->getCustomerAccount();
        if ($account === null) {
            return;
        }

        $subscriptionExpired = $this->suspendExpiredSubscriptionIfNeeded($account);
        $activeSubscription = $this->subscriptionRepository->findActiveForAccount($account);
        if ($activeSubscription instanceof CustomerSubscription) {
            return;
        }

        if ($request->hasSession()) {
            $message = $subscriptionExpired
                ? 'Abonelik sureniz doldugu icin hesap askiya alindi. Devam etmek icin yenileyin.'
                : 'Aktif aboneliginiz yok. Devam etmek icin aboneligi yenileyin.';
            $request->getSession()->getFlashBag()->add('error', $message);
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('admin_account_subscription_renew')));
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
}
