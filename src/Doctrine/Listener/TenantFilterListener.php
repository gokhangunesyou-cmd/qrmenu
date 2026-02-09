<?php

namespace App\Doctrine\Listener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class TenantFilterListener
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Public API and Super Admin API do not use tenant filter
        if (str_starts_with($path, '/api/public') || str_starts_with($path, '/api/super-admin')) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        // Super admins bypass tenant filter
        if ($user->isSuperAdmin()) {
            return;
        }

        $restaurant = $user->getRestaurant();

        if ($restaurant === null || $restaurant->getId() === null) {
            return;
        }

        $filter = $this->em->getFilters()->enable('tenant_filter');
        $filter->setParameter('restaurant_id', $restaurant->getId());
    }
}
