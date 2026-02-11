<?php

namespace App\Twig;

use App\Entity\Restaurant;
use App\Entity\User;
use App\Repository\RestaurantRepository;
use App\Service\LanguageContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminContextExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly RestaurantRepository $restaurantRepository,
        private readonly LanguageContext $languageContext,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_current_restaurant', [$this, 'getCurrentRestaurant']),
            new TwigFunction('admin_selectable_restaurants', [$this, 'getSelectableRestaurants']),
            new TwigFunction('admin_current_locale', [$this, 'getCurrentLocale']),
            new TwigFunction('admin_available_locales', [$this, 'getAvailableLocales']),
        ];
    }

    public function getCurrentRestaurant(): ?Restaurant
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $user->getRestaurant();
    }

    /**
     * @return Restaurant[]
     */
    public function getSelectableRestaurants(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return $this->restaurantRepository->findBy([], ['name' => 'ASC']);
        }

        $restaurants = $user->getRestaurants()->toArray();
        if ($restaurants === [] && $user->getRestaurant() !== null) {
            $restaurants[] = $user->getRestaurant();
        }

        usort($restaurants, static fn (Restaurant $a, Restaurant $b): int => strcmp($a->getName(), $b->getName()));

        return $restaurants;
    }

    public function getCurrentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return 'tr';
        }

        return $this->languageContext->resolveAdminLocale($request, 'tr');
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableLocales(): array
    {
        return $this->languageContext->getLocaleLabelMap();
    }
}
