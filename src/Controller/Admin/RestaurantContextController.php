<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Restaurant;
use App\Entity\User;
use App\Repository\RestaurantRepository;
use App\Service\LanguageContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class RestaurantContextController extends AbstractController
{
    public function __construct(
        private readonly RestaurantRepository $restaurantRepository,
        private readonly EntityManagerInterface $em,
        private readonly LanguageContext $languageContext,
    ) {
    }

    #[Route('/admin/context/restaurant/switch', name: 'admin_switch_restaurant', methods: ['POST'])]
    public function switch(Request $request): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        if (!$this->isCsrfTokenValid('admin_switch_restaurant', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $restaurantId = $request->request->getInt('restaurant_id');
        $restaurant = $this->restaurantRepository->find($restaurantId);
        if (!$restaurant instanceof Restaurant) {
            throw $this->createNotFoundException('Restaurant not found.');
        }

        if (!$user->isSuperAdmin() && !in_array($restaurantId, $user->getAccessibleRestaurantIds(), true)) {
            throw $this->createAccessDeniedException('You cannot access this restaurant.');
        }

        $user->setRestaurant($restaurant);
        $this->em->flush();

        if ($request->hasSession()) {
            $request->getSession()->set('admin.selected_restaurant_id', $restaurant->getId());
            $requestedLocale = strtolower(trim($request->request->getString('lang')));
            $allowedLocales = array_keys($this->languageContext->getLocaleLabelMap($restaurant->getEnabledLocales()));
            if ($requestedLocale === '' || !in_array($requestedLocale, $allowedLocales, true)) {
                $fallback = strtolower($restaurant->getDefaultLocale());
                $requestedLocale = in_array($fallback, $allowedLocales, true)
                    ? $fallback
                    : ($allowedLocales[0] ?? 'tr');
            }

            $request->getSession()->set(LanguageContext::ADMIN_SESSION_KEY, $requestedLocale);
        }

        $target = $request->headers->get('referer');
        if (!$target) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->redirect($target);
    }

    #[Route('/admin/context/language/switch', name: 'admin_switch_language', methods: ['POST'])]
    public function switchLanguage(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_switch_language', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        $currentRestaurant = $user instanceof User ? $user->getRestaurant() : null;

        $locale = strtolower(trim($request->request->getString('lang')));
        $allowed = array_keys($this->languageContext->getLocaleLabelMap($currentRestaurant?->getEnabledLocales()));
        if (!in_array($locale, $allowed, true)) {
            $fallback = strtolower($currentRestaurant?->getDefaultLocale() ?? 'tr');
            $locale = in_array($fallback, $allowed, true)
                ? $fallback
                : ($allowed[0] ?? 'tr');
        }

        if ($request->hasSession()) {
            $request->getSession()->set(LanguageContext::ADMIN_SESSION_KEY, $locale);
        }

        $target = $request->headers->get('referer');
        if (!$target) {
            return $this->redirectToRoute('admin_dashboard', ['lang' => $locale]);
        }

        return $this->redirect($target);
    }
}
