<?php

declare(strict_types=1);

namespace App\Controller\Site;

use App\Entity\Category;
use App\Entity\Restaurant;
use App\Repository\CategoryRepository;
use App\Repository\RestaurantRepository;
use App\Repository\RestaurantSocialLinkRepository;
use App\Service\LanguageContext;
use App\Service\TranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MenuController extends AbstractController
{
    #[Route('/menu/{slug<[^/]+>}-r-{id<\d+>}', name: 'site_menu_show', methods: ['GET'])]
    public function show(
        string $slug,
        int $id,
        Request $request,
        RestaurantRepository $restaurantRepository,
        CategoryRepository $categoryRepository,
        RestaurantSocialLinkRepository $socialLinkRepository,
        TranslationService $translationService,
        LanguageContext $languageContext,
    ): Response {
        $restaurant = $restaurantRepository->findActiveByIdForMenu($id);
        if ($restaurant === null) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        $restaurantLocales = $restaurant->getEnabledLocales();
        $locale = $languageContext->resolveSiteLocale($request, $restaurant->getDefaultLocale(), $restaurantLocales);
        $availableLocales = $languageContext->getLocaleLabelMap($restaurantLocales);
        $canonicalSlug = $restaurant->getSlug();
        $tableNumber = max(0, $request->query->getInt('table', 0));
        if ($slug !== $canonicalSlug) {
            $routeParams = [
                'slug' => $canonicalSlug,
                'id' => $id,
            ];
            if (trim($request->query->getString('lang')) !== '') {
                $routeParams['lang'] = $locale;
            }
            if ($tableNumber > 0) {
                $routeParams['table'] = $tableNumber;
            }

            return $this->redirectToRoute('site_menu_show', $routeParams, Response::HTTP_MOVED_PERMANENTLY);
        }

        $categories = array_values(array_filter(
            $categoryRepository->findActiveWithProductsForMenu($restaurant),
            static fn (Category $category): bool => $category->getProducts()->count() > 0
        ));
        $categoryIds = [];
        $productIds = [];
        foreach ($categories as $category) {
            $categoryId = (int) $category->getId();
            if ($categoryId > 0) {
                $categoryIds[] = $categoryId;
            }

            foreach ($category->getProducts() as $product) {
                $productId = (int) $product->getId();
                if ($productId > 0) {
                    $productIds[] = $productId;
                }
            }
        }

        $categoryTranslations = $translationService->getFieldMapWithFallback(
            'category',
            array_values(array_unique($categoryIds)),
            $locale,
            $restaurant->getDefaultLocale()
        );
        $productTranslations = $translationService->getFieldMapWithFallback(
            'product',
            array_values(array_unique($productIds)),
            $locale,
            $restaurant->getDefaultLocale()
        );

        $localizedCategoryNames = [];
        $localizedCategoryDescriptions = [];
        $localizedProductNames = [];
        $localizedProductDescriptions = [];
        foreach ($categories as $category) {
            $categoryId = (int) $category->getId();
            $localizedCategoryNames[$categoryId] = $translationService->resolve($categoryTranslations, $categoryId, 'name', $category->getName()) ?? $category->getName();
            $localizedCategoryDescriptions[$categoryId] = $translationService->resolve($categoryTranslations, $categoryId, 'description', $category->getDescription());

            foreach ($category->getProducts() as $product) {
                $productId = (int) $product->getId();
                $localizedProductNames[$productId] = $translationService->resolve($productTranslations, $productId, 'name', $product->getName()) ?? $product->getName();
                $localizedProductDescriptions[$productId] = $translationService->resolve($productTranslations, $productId, 'description', $product->getDescription());
            }
        }

        $menuTemplate = $restaurant->getMenuTemplate();
        $templatePath = match ($menuTemplate) {
            'editorial' => 'site/menu/editorial.html.twig',
            default => 'site/menu/showcase.html.twig',
        };

        return $this->render($templatePath, [
            'restaurant' => $restaurant,
            'categories' => $categories,
            'socialLinks' => $socialLinkRepository->findByRestaurant($restaurant),
            'theme' => $this->resolveTheme($restaurant),
            'menuTemplate' => $menuTemplate,
            'currencySymbol' => $this->resolveCurrencySymbol($restaurant->getCurrencyCode()),
            'tableNumber' => $tableNumber > 0 ? $tableNumber : null,
            'currentLocale' => $locale,
            'availableLocales' => $availableLocales,
            'ui' => $this->buildMenuUi($locale),
            'socialPlatformLabels' => $this->buildSocialPlatformLabels($locale),
            'localizedCategoryNames' => $localizedCategoryNames,
            'localizedCategoryDescriptions' => $localizedCategoryDescriptions,
            'localizedProductNames' => $localizedProductNames,
            'localizedProductDescriptions' => $localizedProductDescriptions,
        ]);
    }

    /**
     * @return array{primary:string,secondary:string,background:string,surface:string,text:string,accent:string,muted:string,border:string}
     */
    private function resolveTheme(Restaurant $restaurant): array
    {
        $defaults = $restaurant->getMenuTemplate() === 'editorial'
            ? [
                'primary' => '#0F172A',
                'secondary' => '#475569',
                'background' => '#E2E8F0',
                'surface' => '#F8FAFC',
                'text' => '#020617',
                'accent' => '#F97316',
                'muted' => '#475569',
                'border' => '#CBD5E1',
            ]
            : [
                'primary' => '#9A3412',
                'secondary' => '#0F766E',
                'background' => '#FFF8F1',
                'surface' => '#FFFFFF',
                'text' => '#1F2937',
                'accent' => '#F59E0B',
                'muted' => '#6B7280',
                'border' => '#FCD9BD',
            ];

        $overrides = $restaurant->getColorOverrides();
        if (!is_array($overrides)) {
            return $defaults;
        }

        foreach (['primary', 'secondary', 'background', 'surface', 'text', 'accent'] as $key) {
            if (isset($overrides[$key]) && is_string($overrides[$key])) {
                $defaults[$key] = $this->sanitizeHexColor($overrides[$key], $defaults[$key]);
            }
        }

        return $defaults;
    }

    private function sanitizeHexColor(string $color, string $fallback): string
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? strtoupper($color) : strtoupper($fallback);
    }

    private function resolveCurrencySymbol(string $currencyCode): string
    {
        return match (strtoupper($currencyCode)) {
            'TRY' => 'TRY',
            'USD' => '$',
            'EUR' => 'EUR',
            'GBP' => 'GBP',
            default => strtoupper($currencyCode),
        };
    }

    /**
     * @return array{
     *     menu:string,
     *     categories:string,
     *     contact:string,
     *     social:string,
     *     language:string,
     *     phone:string,
     *     email:string,
     *     address:string,
     *     city:string,
     *     website:string,
     *     maps:string,
     *     table:string,
     *     featured:string,
     *     empty:string,
     *     no_contact:string
     * }
     */
    private function buildMenuUi(string $locale): array
    {
        $labels = [
            'tr' => [
                'menu' => 'Menu',
                'categories' => 'Kategoriler',
                'contact' => 'Iletisim',
                'social' => 'Sosyal Medya',
                'language' => 'Dil',
                'phone' => 'Telefon',
                'email' => 'E-posta',
                'address' => 'Adres',
                'city' => 'Sehir',
                'website' => 'Web Sitesi',
                'maps' => 'Harita',
                'table' => 'Masa',
                'featured' => 'One Cikan',
                'empty' => 'Bu restoran icin henuz yayinlanmis menu urunu yok.',
                'no_contact' => 'Iletisim bilgisi eklenmemis.',
            ],
            'en' => [
                'menu' => 'Menu',
                'categories' => 'Categories',
                'contact' => 'Contact',
                'social' => 'Social',
                'language' => 'Language',
                'phone' => 'Phone',
                'email' => 'Email',
                'address' => 'Address',
                'city' => 'City',
                'website' => 'Website',
                'maps' => 'Map',
                'table' => 'Table',
                'featured' => 'Chef Pick',
                'empty' => 'No published menu items are available for this restaurant yet.',
                'no_contact' => 'No contact details have been added yet.',
            ],
            'ar' => [
                'menu' => 'Menu',
                'categories' => 'Categories',
                'contact' => 'Contact',
                'social' => 'Social',
                'language' => 'Language',
                'phone' => 'Phone',
                'email' => 'Email',
                'address' => 'Address',
                'city' => 'City',
                'website' => 'Website',
                'maps' => 'Map',
                'table' => 'Table',
                'featured' => 'Chef Pick',
                'empty' => 'No published menu items are available for this restaurant yet.',
                'no_contact' => 'No contact details have been added yet.',
            ],
            'ru' => [
                'menu' => 'Menu',
                'categories' => 'Categories',
                'contact' => 'Contact',
                'social' => 'Social',
                'language' => 'Language',
                'phone' => 'Phone',
                'email' => 'Email',
                'address' => 'Address',
                'city' => 'City',
                'website' => 'Website',
                'maps' => 'Map',
                'table' => 'Table',
                'featured' => 'Chef Pick',
                'empty' => 'No published menu items are available for this restaurant yet.',
                'no_contact' => 'No contact details have been added yet.',
            ],
        ];

        return $labels[$locale] ?? $labels['en'];
    }

    /**
     * @return array<string, string>
     */
    private function buildSocialPlatformLabels(string $locale): array
    {
        $ui = $this->buildMenuUi($locale);

        return [
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'twitter' => 'X / Twitter',
            'tiktok' => 'TikTok',
            'website' => $ui['website'],
            'google_maps' => $ui['maps'],
        ];
    }
}
