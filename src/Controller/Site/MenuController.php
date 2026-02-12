<?php

declare(strict_types=1);

namespace App\Controller\Site;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Restaurant;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
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
        $whatsappNumber = $restaurant->isWhatsappOrderEnabled()
            ? $this->normalizeWhatsappNumber($restaurant->getPhone())
            : null;

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
            'whatsappNumber' => $whatsappNumber,
            'isWhatsappSettingEnabled' => $restaurant->isWhatsappOrderEnabled(),
            'isWhatsappOrderEnabled' => $whatsappNumber !== null,
        ]);
    }

    #[Route('/menu/{slug<[^/]+>}-r-{id<\d+>}/product/{productUuid}', name: 'site_menu_product_detail', methods: ['GET'])]
    public function detail(
        string $slug,
        int $id,
        string $productUuid,
        Request $request,
        RestaurantRepository $restaurantRepository,
        ProductRepository $productRepository,
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
                'productUuid' => $productUuid,
            ];
            if (trim($request->query->getString('lang')) !== '') {
                $routeParams['lang'] = $locale;
            }
            if ($tableNumber > 0) {
                $routeParams['table'] = $tableNumber;
            }

            return $this->redirectToRoute('site_menu_product_detail', $routeParams, Response::HTTP_MOVED_PERMANENTLY);
        }

        $product = $productRepository->findActiveByUuidForMenu($restaurant, $productUuid);
        if (!$product instanceof Product) {
            throw $this->createNotFoundException('Product not found');
        }

        if ($restaurant->isCountProductDetailViews()) {
            $productRepository->incrementDetailViewCount($product);
        }

        $category = $product->getCategory();
        $categoryId = (int) $category->getId();
        $productId = (int) $product->getId();

        $categoryTranslations = $translationService->getFieldMapWithFallback(
            'category',
            $categoryId > 0 ? [$categoryId] : [],
            $locale,
            $restaurant->getDefaultLocale()
        );
        $productTranslations = $translationService->getFieldMapWithFallback(
            'product',
            $productId > 0 ? [$productId] : [],
            $locale,
            $restaurant->getDefaultLocale()
        );

        $localizedCategoryName = $translationService->resolve($categoryTranslations, $categoryId, 'name', $category->getName()) ?? $category->getName();
        $localizedProductName = $translationService->resolve($productTranslations, $productId, 'name', $product->getName()) ?? $product->getName();
        $localizedProductDescription = $translationService->resolve($productTranslations, $productId, 'description', $product->getDescription());
        $allergenLabels = $this->buildAllergenLabels($locale);
        $localizedAllergens = [];
        foreach ($product->getAllergens() ?? [] as $allergen) {
            if (!is_string($allergen) || trim($allergen) === '') {
                continue;
            }

            $key = trim($allergen);
            $localizedAllergens[] = $allergenLabels[$key] ?? $key;
        }

        $menuRouteParams = [
            'slug' => $restaurant->getSlug(),
            'id' => (int) $restaurant->getId(),
            'lang' => $locale,
        ];
        if ($tableNumber > 0) {
            $menuRouteParams['table'] = $tableNumber;
        }

        $whatsappNumber = $restaurant->isWhatsappOrderEnabled()
            ? $this->normalizeWhatsappNumber($restaurant->getPhone())
            : null;

        return $this->render('site/menu/detail.html.twig', [
            'restaurant' => $restaurant,
            'product' => $product,
            'socialLinks' => $socialLinkRepository->findByRestaurant($restaurant),
            'theme' => $this->resolveTheme($restaurant),
            'menuTemplate' => $restaurant->getMenuTemplate(),
            'currencySymbol' => $this->resolveCurrencySymbol($restaurant->getCurrencyCode()),
            'tableNumber' => $tableNumber > 0 ? $tableNumber : null,
            'currentLocale' => $locale,
            'availableLocales' => $availableLocales,
            'ui' => $this->buildMenuUi($locale),
            'socialPlatformLabels' => $this->buildSocialPlatformLabels($locale),
            'allergenLabels' => $allergenLabels,
            'localizedCategoryName' => $localizedCategoryName,
            'localizedProductName' => $localizedProductName,
            'localizedProductDescription' => $localizedProductDescription,
            'localizedAllergens' => $localizedAllergens,
            'menuRouteParams' => $menuRouteParams,
            'whatsappNumber' => $whatsappNumber,
            'isWhatsappSettingEnabled' => $restaurant->isWhatsappOrderEnabled(),
            'isWhatsappOrderEnabled' => $whatsappNumber !== null,
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
     *     no_contact:string,
     *     detail:string,
     *     back_to_menu:string,
     *     product_details:string,
     *     category_label:string,
     *     price_label:string,
     *     description_label:string,
     *     calories_label:string,
     *     allergens_label:string,
     *     add_to_cart:string,
     *     cart:string,
     *     quantity:string,
     *     empty_cart:string,
     *     clear_cart:string,
     *     send_whatsapp:string,
     *     whatsapp_unavailable:string
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
                'detail' => 'Detay',
                'back_to_menu' => 'Menuye Don',
                'product_details' => 'Urun Detaylari',
                'category_label' => 'Kategori',
                'price_label' => 'Fiyat',
                'description_label' => 'Aciklama',
                'calories_label' => 'Kalori',
                'allergens_label' => 'Alerjenler',
                'add_to_cart' => 'Sepete Ekle',
                'cart' => 'Sepet',
                'quantity' => 'Adet',
                'empty_cart' => 'Sepetiniz bos.',
                'clear_cart' => 'Sepeti Temizle',
                'send_whatsapp' => 'WhatsApp ile Siparis Gonder',
                'whatsapp_unavailable' => 'WhatsApp siparisi icin restoran telefonu tanimli olmali.',
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
                'detail' => 'Details',
                'back_to_menu' => 'Back to Menu',
                'product_details' => 'Product Details',
                'category_label' => 'Category',
                'price_label' => 'Price',
                'description_label' => 'Description',
                'calories_label' => 'Calories',
                'allergens_label' => 'Allergens',
                'add_to_cart' => 'Add to Cart',
                'cart' => 'Cart',
                'quantity' => 'Qty',
                'empty_cart' => 'Your cart is empty.',
                'clear_cart' => 'Clear Cart',
                'send_whatsapp' => 'Send Order via WhatsApp',
                'whatsapp_unavailable' => 'Restaurant phone is required for WhatsApp ordering.',
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
                'detail' => 'Details',
                'back_to_menu' => 'Back to Menu',
                'product_details' => 'Product Details',
                'category_label' => 'Category',
                'price_label' => 'Price',
                'description_label' => 'Description',
                'calories_label' => 'Calories',
                'allergens_label' => 'Allergens',
                'add_to_cart' => 'Add to Cart',
                'cart' => 'Cart',
                'quantity' => 'Qty',
                'empty_cart' => 'Your cart is empty.',
                'clear_cart' => 'Clear Cart',
                'send_whatsapp' => 'Send Order via WhatsApp',
                'whatsapp_unavailable' => 'Restaurant phone is required for WhatsApp ordering.',
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
                'detail' => 'Details',
                'back_to_menu' => 'Back to Menu',
                'product_details' => 'Product Details',
                'category_label' => 'Category',
                'price_label' => 'Price',
                'description_label' => 'Description',
                'calories_label' => 'Calories',
                'allergens_label' => 'Allergens',
                'add_to_cart' => 'Add to Cart',
                'cart' => 'Cart',
                'quantity' => 'Qty',
                'empty_cart' => 'Your cart is empty.',
                'clear_cart' => 'Clear Cart',
                'send_whatsapp' => 'Send Order via WhatsApp',
                'whatsapp_unavailable' => 'Restaurant phone is required for WhatsApp ordering.',
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

    /**
     * @return array<string, string>
     */
    private function buildAllergenLabels(string $locale): array
    {
        $labels = [
            'tr' => [
                'gluten' => 'Gluten',
                'sut' => 'Sut',
                'yumurta' => 'Yumurta',
                'fistik' => 'Fistik',
                'soya' => 'Soya',
                'balik' => 'Balik',
                'kabuklu_deniz_urunleri' => 'Kabuklu Deniz Urunleri',
                'kereviz' => 'Kereviz',
            ],
            'en' => [
                'gluten' => 'Gluten',
                'sut' => 'Milk',
                'yumurta' => 'Egg',
                'fistik' => 'Peanut',
                'soya' => 'Soy',
                'balik' => 'Fish',
                'kabuklu_deniz_urunleri' => 'Shellfish',
                'kereviz' => 'Celery',
            ],
        ];

        return $labels[$locale] ?? $labels['en'];
    }

    private function normalizeWhatsappNumber(?string $phone): ?string
    {
        if (!is_string($phone) || trim($phone) === '') {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $phone);
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = substr($normalized, 2);
        }

        return $normalized !== '' ? $normalized : null;
    }
}
