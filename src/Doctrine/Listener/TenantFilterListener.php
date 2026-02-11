<?php

namespace App\Doctrine\Listener;

use App\Entity\User;
use App\Repository\RestaurantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class TenantFilterListener
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly RestaurantRepository $restaurantRepository,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        $restaurantIds = $user->getAccessibleRestaurantIds();
        if ($restaurantIds === [] && !$user->isSuperAdmin()) {
            return;
        }

        $selectedRestaurantId = null;
        if ($request->hasSession()) {
            $selectedRestaurantId = $request->getSession()->get('admin.selected_restaurant_id');
        }

        if ($user->isSuperAdmin()) {
            $restaurant = null;
            if (is_numeric($selectedRestaurantId)) {
                $restaurant = $this->restaurantRepository->find((int) $selectedRestaurantId);
            }

            if ($restaurant === null) {
                $restaurants = $this->restaurantRepository->findBy([], ['name' => 'ASC'], 1);
                $restaurant = $restaurants[0] ?? null;
                if ($restaurant !== null && $request->hasSession()) {
                    $request->getSession()->set('admin.selected_restaurant_id', $restaurant->getId());
                }
            }

            if ($restaurant !== null) {
                $user->setRestaurant($restaurant);
            }

            return;
        }

        if (!is_numeric($selectedRestaurantId) || !in_array((int) $selectedRestaurantId, $restaurantIds, true)) {
            $selectedRestaurantId = $restaurantIds[0];
            if ($request->hasSession()) {
                $request->getSession()->set('admin.selected_restaurant_id', $selectedRestaurantId);
            }
        } else {
            $selectedRestaurantId = (int) $selectedRestaurantId;
        }

        if ($user->getRestaurant()?->getId() !== $selectedRestaurantId) {
            foreach ($user->getRestaurants() as $restaurant) {
                if ($restaurant->getId() === $selectedRestaurantId) {
                    $user->setRestaurant($restaurant);
                    break;
                }
            }
        }

        $filter = $this->em->getFilters()->enable('tenant_filter');
        $filter->setParameter('restaurant_id', (int) $selectedRestaurantId);
    }
}
