<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\BlogComment;
use App\Entity\BlogPost;
use App\Entity\CustomerAccount;
use App\Entity\CustomerSubscription;
use App\Entity\Plan;
use App\Entity\Restaurant;
use App\Entity\SiteContent;
use App\Entity\User;
use App\Repository\BlogCommentRepository;
use App\Repository\BlogPostRepository;
use App\Repository\CustomerAccountRepository;
use App\Repository\CustomerSubscriptionRepository;
use App\Repository\PlanRepository;
use App\Repository\RestaurantRepository;
use App\Repository\RoleRepository;
use App\Repository\SiteContentRepository;
use App\Repository\SiteSettingRepository;
use App\Repository\SiteWidgetRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserRepository;
use App\Service\CacheManagementService;
use App\Service\LanguageContext;
use App\Service\SiteThemeRegistry;
use App\Service\SiteWidgetRegistry;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/super')]
class SuperAdminWebController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlanRepository $planRepository,
        private readonly RestaurantRepository $restaurantRepository,
        private readonly BlogPostRepository $blogPostRepository,
        private readonly BlogCommentRepository $blogCommentRepository,
        private readonly SiteContentRepository $siteContentRepository,
        private readonly SiteWidgetRepository $siteWidgetRepository,
        private readonly SiteSettingRepository $siteSettingRepository,
        private readonly ThemeRepository $themeRepository,
        private readonly CustomerAccountRepository $customerAccountRepository,
        private readonly CustomerSubscriptionRepository $subscriptionRepository,
        private readonly RoleRepository $roleRepository,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TranslationService $translationService,
        private readonly LanguageContext $languageContext,
        private readonly SiteWidgetRegistry $siteWidgetRegistry,
        private readonly SiteThemeRegistry $siteThemeRegistry,
        private readonly CacheManagementService $cacheManagementService,
    ) {
    }

    #[Route('', name: 'admin_super_index', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->redirectToRoute('admin_super_plans');
    }

    #[Route('/plans', name: 'admin_super_plans', methods: ['GET'])]
    public function plans(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->render('admin/super/plans.html.twig', [
            'plans' => $this->planRepository->findBy([], ['yearlyPrice' => 'ASC']),
            'restaurants' => $this->restaurantRepository->findBy([], ['createdAt' => 'DESC']),
            'contents' => $this->siteContentRepository->findBy([], ['keyName' => 'ASC']),
            'accounts' => $this->customerAccountRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/slider', name: 'admin_super_slider', methods: ['GET'])]
    public function slider(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');
        $sliderContents = $this->siteContentRepository->findByPrefix('home_slider_');
        $contentIds = array_values(array_map(static fn (SiteContent $content): int => (int) $content->getId(), $sliderContents));
        $translations = $this->translationService->getFieldMap('site_content', $contentIds, $locale);
        $sliderRows = array_map(fn (SiteContent $content): array => $this->mapSliderContent($content, $translations), $sliderContents);

        return $this->render('admin/super/slider.html.twig', [
            'sliderRows' => $sliderRows,
            'nextSliderKey' => $this->buildNextSliderKey($sliderContents),
            'currentLocale' => $locale,
        ]);
    }

    #[Route('/cms', name: 'admin_super_cms', methods: ['GET'])]
    public function cms(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');
        $cmsConfig = $this->homeCmsFieldConfig();
        $contents = $this->siteContentRepository->findByPrefix('home_');
        $cmsContents = [];
        $contentIds = array_values(array_map(static fn (SiteContent $content): int => (int) $content->getId(), $contents));
        $translations = $this->translationService->getFieldMap('site_content', $contentIds, $locale);

        foreach ($contents as $content) {
            if (str_starts_with($content->getKeyName(), 'home_slider_')) {
                continue;
            }
            $cmsContents[$content->getKeyName()] = $content;
        }

        $cmsValues = [];
        foreach ($cmsContents as $key => $content) {
            $contentId = (int) $content->getId();
            $cmsValues[$key] = $this->translationService->resolve($translations, $contentId, 'body', $content->getBody()) ?? $content->getBody();
        }

        return $this->render('admin/super/cms.html.twig', [
            'cmsConfig' => $cmsConfig,
            'cmsContents' => $cmsContents,
            'cmsValues' => $cmsValues,
            'currentLocale' => $locale,
        ]);
    }

    #[Route('/site-theme', name: 'admin_super_site_theme', methods: ['GET'])]
    public function siteTheme(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');
        $themes = $this->siteThemeRegistry->getThemes();

        $activeTheme = $this->siteSettingRepository->findOneByKey('site_theme_active');
        $activeKey = $activeTheme?->getValue() ?: 'theme_1';

        $paletteSetting = $this->siteSettingRepository->findOneByKey('site_theme_palette');
        $paletteValue = $paletteSetting?->getValue() ?: '';
        if ($paletteValue === '') {
            $paletteValue = json_encode($this->siteThemeRegistry->getDefaultPalette($activeKey), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }

        return $this->render('admin/super/site_theme.html.twig', [
            'themes' => $themes,
            'activeKey' => $activeKey,
            'paletteValue' => $paletteValue,
            'currentLocale' => $locale,
        ]);
    }

    #[Route('/site-theme/update', name: 'admin_super_site_theme_update', methods: ['POST'])]
    public function updateSiteTheme(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_site_theme_update');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $themeKey = trim($request->request->getString('active_theme'));
        $themes = $this->siteThemeRegistry->getThemes();
        if (!isset($themes[$themeKey])) {
            $this->addFlash('error', 'Gecerli bir tema seciniz.');
            return $this->redirectToRoute('admin_super_site_theme', ['lang' => $locale]);
        }

        $palette = trim($request->request->getString('palette_json'));
        if ($palette === '') {
            $palette = json_encode($this->siteThemeRegistry->getDefaultPalette($themeKey), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }

        $decoded = json_decode($palette, true);
        if (!is_array($decoded)) {
            $this->addFlash('error', 'Renk paleti JSON formatinda olmali.');
            return $this->redirectToRoute('admin_super_site_theme', ['lang' => $locale]);
        }

        $activeSetting = $this->siteSettingRepository->findOneByKey('site_theme_active');
        if (!$activeSetting) {
            $activeSetting = new \App\Entity\SiteSetting('site_theme_active', $themeKey);
            $this->em->persist($activeSetting);
        } else {
            $activeSetting->setValue($themeKey);
            $activeSetting->touch();
        }

        $paletteSetting = $this->siteSettingRepository->findOneByKey('site_theme_palette');
        if (!$paletteSetting) {
            $paletteSetting = new \App\Entity\SiteSetting('site_theme_palette', $palette);
            $this->em->persist($paletteSetting);
        } else {
            $paletteSetting->setValue($palette);
            $paletteSetting->touch();
        }

        $this->em->flush();
        $this->addFlash('success', 'Site temasi guncellendi.');

        return $this->redirectToRoute('admin_super_site_theme', ['lang' => $locale]);
    }

    #[Route('/widgets', name: 'admin_super_widgets', methods: ['GET'])]
    public function widgets(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');
        $definitions = $this->siteWidgetRegistry->getDefinitions();
        $widgets = $this->siteWidgetRepository->findAllOrdered();
        $widgetIds = array_values(array_map(static fn ($widget): int => (int) $widget->getId(), $widgets));
        $translations = $this->translationService->getFieldMap('site_widget', $widgetIds, $locale);

        $rows = [];
        foreach ($widgets as $widget) {
            $definition = $definitions[$widget->getType()] ?? null;
            if ($definition === null) {
                continue;
            }
            $config = $this->siteWidgetRegistry->mergeTranslations($widget->getConfig(), $translations, (int) $widget->getId(), $definition);
            $config = $this->siteWidgetRegistry->normalizeConfig($config, $definition);
            $rows[] = [
                'widget' => $widget,
                'definition' => $definition,
                'values' => $this->siteWidgetRegistry->exportFormValues($config, $definition['fields']),
            ];
        }

        return $this->render('admin/super/widgets.html.twig', [
            'definitions' => $definitions,
            'widgets' => $rows,
            'currentLocale' => $locale,
        ]);
    }

    #[Route('/widgets/create', name: 'admin_super_widget_create', methods: ['POST'])]
    public function createWidget(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_widget_create');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $type = trim($request->request->getString('type'));
        $keyName = trim($request->request->getString('key_name'));
        $sortOrder = $request->request->getInt('sort_order', 0);
        $definition = $this->siteWidgetRegistry->getDefinition($type);

        if ($definition === null) {
            $this->addFlash('error', 'Gecerli bir widget tipi seciniz.');
            return $this->redirectToRoute('admin_super_widgets', ['lang' => $locale]);
        }
        if ($keyName === '') {
            $this->addFlash('error', 'Widget key zorunludur.');
            return $this->redirectToRoute('admin_super_widgets', ['lang' => $locale]);
        }
        if ($this->siteWidgetRepository->findOneBy(['keyName' => $keyName])) {
            $this->addFlash('error', 'Bu key ile bir widget zaten mevcut.');
            return $this->redirectToRoute('admin_super_widgets', ['lang' => $locale]);
        }

        $jsonError = $this->validateWidgetJsonFields($request, $definition);
        if ($jsonError !== null) {
            $this->addFlash('error', $jsonError);
            return $this->redirectToRoute('admin_super_widgets', ['lang' => $locale]);
        }

        $config = $this->buildWidgetConfig($request, $definition);
        $widget = new \App\Entity\SiteWidget($keyName, $type, $config);
        $widget->setSortOrder($sortOrder);
        $widget->setIsActive($request->request->getBoolean('is_active', true));

        $this->em->persist($widget);
        $this->em->flush();

        $this->upsertWidgetTranslations($widget, $definition, $locale, $request);
        $this->em->flush();

        $this->addFlash('success', 'Widget eklendi.');

        return $this->redirectToRoute('admin_super_widgets', ['lang' => $locale]);
    }

    #[Route('/widgets/{id}/update', name: 'admin_super_widget_update', methods: ['POST'])]
    public function updateWidget(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_widget_update_' . $id);
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $widget = $this->siteWidgetRepository->find($id);
        if (!$widget instanceof \App\Entity\SiteWidget) {
            throw $this->createNotFoundException('Widget not found.');
        }

        $definition = $this->siteWidgetRegistry->getDefinition($widget->getType());
        if ($definition === null) {
            $this->addFlash('error', 'Widget tipi bulunamadi.');
            return $this->redirectToRoute('admin_super_widgets', ['lang' => $locale]);
        }

        $keyName = trim($request->request->getString('key_name'));
        if ($keyName === '') {
            $this->addFlash('error', 'Widget key zorunludur.');
            return $this->redirectToRoute('admin_super_widgets', ['lang' => $locale]);
        }

        $existing = $this->siteWidgetRepository->findOneBy(['keyName' => $keyName]);
        if ($existing && $existing->getId() !== $widget->getId()) {
            $this->addFlash('error', 'Bu key ile baska bir widget mevcut.');
            return $this->redirectToRoute('admin_super_widgets', ['lang' => $locale]);
        }

        $jsonError = $this->validateWidgetJsonFields($request, $definition);
        if ($jsonError !== null) {
            $this->addFlash('error', $jsonError);
            return $this->redirectToRoute('admin_super_widgets', ['lang' => $locale]);
        }

        $widget->setKeyName($keyName);
        $widget->setSortOrder($request->request->getInt('sort_order', $widget->getSortOrder()));
        $widget->setIsActive($request->request->getBoolean('is_active', $widget->isActive()));
        if ($locale === 'tr') {
            $widget->setConfig($this->buildWidgetConfig($request, $definition));
        }
        $widget->touch();

        $this->upsertWidgetTranslations($widget, $definition, $locale, $request);
        $this->em->flush();

        $this->addFlash('success', 'Widget guncellendi.');

        return $this->redirectToRoute('admin_super_widgets', ['lang' => $locale]);
    }

    #[Route('/widgets/{id}/delete', name: 'admin_super_widget_delete', methods: ['POST'])]
    public function deleteWidget(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_widget_delete_' . $id);
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $widget = $this->siteWidgetRepository->find($id);
        if ($widget) {
            $this->em->remove($widget);
            $this->em->flush();
        }

        return $this->redirectToRoute('admin_super_widgets', ['lang' => $locale]);
    }

    #[Route('/blog', name: 'admin_super_blog_index', methods: ['GET'])]
    public function blog(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');
        $posts = $this->blogPostRepository->findBy([], ['createdAt' => 'DESC']);
        $postIds = array_values(array_map(static fn (BlogPost $post): int => (int) $post->getId(), $posts));
        $translations = $this->translationService->getFieldMap('blog_post', $postIds, $locale);

        $postView = [];
        foreach ($posts as $post) {
            $postId = (int) $post->getId();
            $postView[$postId] = [
                'title' => $this->translationService->resolve($translations, $postId, 'title', $post->getTitle()) ?? $post->getTitle(),
                'body' => $this->translationService->resolve($translations, $postId, 'body', $post->getBody()) ?? $post->getBody(),
                'metaTitle' => $this->translationService->resolve($translations, $postId, 'meta_title', $post->getMetaTitle()),
                'metaDescription' => $this->translationService->resolve($translations, $postId, 'meta_description', $post->getMetaDescription()),
            ];
        }

        return $this->render('admin/super/blog.html.twig', [
            'posts' => $posts,
            'postView' => $postView,
            'pendingCommentCount' => $this->blogCommentRepository->countPending(),
            'currentLocale' => $locale,
        ]);
    }

    #[Route('/blog/comments', name: 'admin_super_blog_comments', methods: ['GET'])]
    public function blogComments(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        return $this->render('admin/super/blog_comments.html.twig', [
            'comments' => $this->blogCommentRepository->findAllForModeration(),
            'currentLocale' => $locale,
        ]);
    }

    #[Route('/cache', name: 'admin_super_cache', methods: ['GET'])]
    public function cache(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $selectedPool = trim($request->query->getString('pool'));
        $query = trim($request->query->getString('q'));
        $poolChoices = $this->cacheManagementService->getPoolChoices();

        if ($selectedPool !== '' && !isset($poolChoices[$selectedPool])) {
            $selectedPool = '';
        }

        try {
            $keys = $this->cacheManagementService->listCacheKeys($selectedPool !== '' ? $selectedPool : null, $query);
        } catch (\Throwable) {
            $keys = [];
            $this->addFlash('error', 'Cache anahtarlari su anda listelenemiyor.');
        }

        return $this->render('admin/super/cache.html.twig', [
            'currentLocale' => $locale,
            'keys' => $keys,
            'poolChoices' => $poolChoices,
            'selectedPool' => $selectedPool,
            'query' => $query,
        ]);
    }

    #[Route('/cache/delete-selected', name: 'admin_super_cache_delete_selected', methods: ['POST'])]
    public function deleteSelectedCacheKeys(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_cache_delete_selected');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $submittedKeys = $request->request->all('keys');
        $keys = [];

        if (is_array($submittedKeys)) {
            foreach ($submittedKeys as $submittedKey) {
                if (!is_string($submittedKey)) {
                    continue;
                }

                $key = trim($submittedKey);
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }

        if ($keys === []) {
            $this->addFlash('error', 'Silinecek en az bir cache key seciniz.');
            return $this->redirectToRoute('admin_super_cache', $this->buildCacheRedirectParams($request, $locale));
        }

        try {
            $deletedCount = $this->cacheManagementService->deleteRawKeys($keys);
        } catch (\Throwable) {
            $this->addFlash('error', 'Secili cache keyleri silinemedi.');
            return $this->redirectToRoute('admin_super_cache', $this->buildCacheRedirectParams($request, $locale));
        }

        if ($deletedCount > 0) {
            $this->addFlash('success', sprintf('%d cache key silindi.', $deletedCount));
        } else {
            $this->addFlash('error', 'Secili keyler silinemedi veya zaten silinmis.');
        }

        return $this->redirectToRoute('admin_super_cache', $this->buildCacheRedirectParams($request, $locale));
    }

    #[Route('/cache/delete-single', name: 'admin_super_cache_delete_single', methods: ['POST'])]
    public function deleteSingleCacheKey(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_cache_delete_single');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $key = trim($request->request->getString('key'));
        if ($key === '') {
            $this->addFlash('error', 'Cache key bos olamaz.');
            return $this->redirectToRoute('admin_super_cache', $this->buildCacheRedirectParams($request, $locale));
        }

        try {
            $deletedCount = $this->cacheManagementService->deleteRawKeys([$key]);
        } catch (\Throwable) {
            $this->addFlash('error', 'Cache key silinemedi.');
            return $this->redirectToRoute('admin_super_cache', $this->buildCacheRedirectParams($request, $locale));
        }

        if ($deletedCount > 0) {
            $this->addFlash('success', 'Cache key silindi.');
        } else {
            $this->addFlash('error', 'Cache key bulunamadi veya silinemedi.');
        }

        return $this->redirectToRoute('admin_super_cache', $this->buildCacheRedirectParams($request, $locale));
    }

    #[Route('/cache/clear', name: 'admin_super_cache_clear', methods: ['POST'])]
    public function clearCachePools(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_cache_clear');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $selectedPool = trim($request->request->getString('pool'));

        try {
            $clearedPools = $this->cacheManagementService->clearPools($selectedPool !== '' ? $selectedPool : null);
        } catch (\Throwable) {
            $this->addFlash('error', 'Cache temizleme islemi basarisiz oldu.');
            return $this->redirectToRoute('admin_super_cache', $this->buildCacheRedirectParams($request, $locale));
        }

        if ($clearedPools > 0) {
            $this->addFlash('success', sprintf('%d cache havuzu temizlendi.', $clearedPools));
        } else {
            $this->addFlash('error', 'Temizlenecek cache havuzu bulunamadi.');
        }

        return $this->redirectToRoute('admin_super_cache', $this->buildCacheRedirectParams($request, $locale));
    }

    #[Route('/subscriptions', name: 'admin_super_subscriptions', methods: ['GET'])]
    public function subscriptions(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $today = new \DateTimeImmutable('today');
        $subscriptions = $this->subscriptionRepository->findBy([], ['endsAt' => 'ASC', 'id' => 'DESC']);

        $rows = array_map(
            static function (CustomerSubscription $subscription) use ($today): array {
                $account = $subscription->getCustomerAccount();
                $endsAt = $subscription->getEndsAt();
                $daysRemaining = (int) $today->diff($endsAt)->format('%r%a');

                $status = !$subscription->isActive()
                    ? 'Pasif'
                    : ($daysRemaining < 0 ? 'Suresi dolmus' : 'Aktif');

                return [
                    'subscription' => $subscription,
                    'account' => $account,
                    'plan' => $subscription->getPlan(),
                    'status' => $status,
                    'daysRemaining' => $daysRemaining,
                ];
            },
            $subscriptions,
        );

        return $this->render('admin/super/subscriptions.html.twig', [
            'rows' => $rows,
            'today' => $today,
            'plans' => $this->planRepository->findBy([], ['yearlyPrice' => 'ASC']),
        ]);
    }

    #[Route('/subscriptions/create', name: 'admin_super_subscription_create', methods: ['POST'])]
    public function createSubscriber(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_subscription_create');

        $accountName = trim($request->request->getString('account_name'));
        $email = trim($request->request->getString('email'));
        $firstName = trim($request->request->getString('first_name', 'Isletme'));
        $lastName = trim($request->request->getString('last_name', 'Yoneticisi'));
        $planId = $request->request->getInt('plan_id');
        $startsAt = $this->parseDateOrNull($request->request->getString('starts_at'));
        $endsAt = $this->parseDateOrNull($request->request->getString('ends_at'));

        if ($accountName === '' || $email === '' || $firstName === '' || $lastName === '' || $planId <= 0 || !$startsAt instanceof \DateTimeImmutable || !$endsAt instanceof \DateTimeImmutable) {
            $this->addFlash('error', 'Yeni abone icin tum alanlari dogru doldurunuz.');
            return $this->redirectToRoute('admin_super_subscriptions');
        }

        if ($endsAt < $startsAt) {
            $this->addFlash('error', 'Abonelik bitis tarihi baslangic tarihinden once olamaz.');
            return $this->redirectToRoute('admin_super_subscriptions');
        }

        if ($this->userRepository->findByEmail($email) instanceof User) {
            $this->addFlash('error', 'Bu e-posta ile kayitli bir kullanici zaten var.');
            return $this->redirectToRoute('admin_super_subscriptions');
        }

        $plan = $this->planRepository->find($planId);
        if (!$plan instanceof Plan) {
            $this->addFlash('error', 'Gecerli bir plan seciniz.');
            return $this->redirectToRoute('admin_super_subscriptions');
        }

        $ownerRole = $this->roleRepository->findOneBy(['name' => 'ROLE_RESTAURANT_OWNER']);
        if ($ownerRole === null) {
            throw $this->createNotFoundException('ROLE_RESTAURANT_OWNER role not found.');
        }

        $temporaryPassword = $this->generateTemporaryPassword();

        $account = new CustomerAccount($accountName);
        $account->setEmail($email);

        $user = new User($email, '', $firstName, $lastName);
        $user->setCustomerAccount($account);
        $user->addRole($ownerRole);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $temporaryPassword));

        $subscription = new CustomerSubscription($account, $plan, $startsAt, $endsAt);
        $subscription->setIsActive($request->request->getBoolean('is_active'));

        $this->em->persist($account);
        $this->em->persist($user);
        $this->em->persist($subscription);
        $this->em->flush();

        $mailSent = $this->sendWelcomePasswordEmail($email, $accountName, $temporaryPassword);
        if ($mailSent) {
            $this->addFlash('success', sprintf('%s icin yeni abone olusturuldu. Sifre e-posta ile gonderildi.', $accountName));
        } else {
            $this->addFlash('error', sprintf('%s olusturuldu ancak e-posta gonderimi basarisiz oldu. Gecici sifre: %s', $accountName, $temporaryPassword));
        }

        return $this->redirectToRoute('admin_super_subscriptions');
    }

    #[Route('/subscriptions/{id}/update', name: 'admin_super_subscription_update', methods: ['POST'])]
    public function updateSubscription(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_subscription_update_' . $id);

        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription instanceof CustomerSubscription) {
            throw $this->createNotFoundException('Abonelik bulunamadi.');
        }

        $planId = $request->request->getInt('plan_id');
        $plan = $this->planRepository->find($planId);
        $startsAt = $this->parseDateOrNull($request->request->getString('starts_at'));
        $endsAt = $this->parseDateOrNull($request->request->getString('ends_at'));

        if (!$plan instanceof Plan || !$startsAt instanceof \DateTimeImmutable || !$endsAt instanceof \DateTimeImmutable) {
            $this->addFlash('error', 'Plan ve tarih bilgilerini gecerli giriniz.');
            return $this->redirectToRoute('admin_super_subscriptions');
        }

        if ($endsAt < $startsAt) {
            $this->addFlash('error', 'Abonelik bitis tarihi baslangic tarihinden once olamaz.');
            return $this->redirectToRoute('admin_super_subscriptions');
        }

        $subscription->setPlan($plan);
        $subscription->setStartsAt($startsAt);
        $subscription->setEndsAt($endsAt);
        $subscription->setIsActive($request->request->getBoolean('is_active'));

        $this->em->flush();

        $this->addFlash('success', 'Abonelik plani ve tarihleri guncellendi.');

        return $this->redirectToRoute('admin_super_subscriptions');
    }

    #[Route('/plans/create', name: 'admin_super_plan_create', methods: ['POST'])]
    public function createPlan(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_plan_create');

        $plan = new Plan(
            code: trim($request->request->getString('code')),
            name: trim($request->request->getString('name')),
            maxRestaurants: max(1, $request->request->getInt('max_restaurants', 1)),
            maxUsers: max(1, $request->request->getInt('max_users', 1)),
            yearlyPrice: $request->request->getString('yearly_price', '0'),
        );
        $plan->setDescription(trim($request->request->getString('description')) ?: null);
        $plan->setIsActive($request->request->getBoolean('is_active'));

        $this->em->persist($plan);
        $this->em->flush();

        return $this->redirectToRoute('admin_super_plans');
    }

    #[Route('/plans/{id}/update', name: 'admin_super_plan_update', methods: ['POST'])]
    public function updatePlan(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_plan_update_' . $id);

        $plan = $this->planRepository->find($id);
        if (!$plan) {
            throw $this->createNotFoundException('Plan not found.');
        }

        $plan->setDescription(trim($request->request->getString('description')) ?: null);
        $plan->setMaxRestaurants(max(1, $request->request->getInt('max_restaurants', 1)));
        $plan->setMaxUsers(max(1, $request->request->getInt('max_users', 1)));
        $plan->setYearlyPrice($request->request->getString('yearly_price', '0'));
        $plan->setIsActive($request->request->getBoolean('is_active'));
        $plan->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $this->redirectToRoute('admin_super_plans');
    }

    #[Route('/plans/{id}/delete', name: 'admin_super_plan_delete', methods: ['POST'])]
    public function deletePlan(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_plan_delete_' . $id);

        $plan = $this->planRepository->find($id);
        if ($plan) {
            $this->em->remove($plan);
            $this->em->flush();
        }

        return $this->redirectToRoute('admin_super_plans');
    }

    #[Route('/restaurants/create', name: 'admin_super_restaurant_create', methods: ['POST'])]
    public function createRestaurant(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_restaurant_create');

        $name = trim($request->request->getString('name'));
        if ($name === '') {
            throw $this->createNotFoundException('Restaurant name is required.');
        }

        $theme = $this->themeRepository->findAllActive()[0] ?? null;
        if ($theme === null) {
            throw $this->createNotFoundException('At least one active theme is required.');
        }

        $slug = $this->buildUniqueRestaurantSlug($name);
        $restaurant = new Restaurant($name, $slug, $theme);
        $restaurant->setIsActive($request->request->getBoolean('is_active'));
        $restaurant->setEmail(trim($request->request->getString('email')) ?: null);

        $account = $this->resolveAccountForRestaurant($request);
        if ($account !== null) {
            $restaurant->setCustomerAccount($account);
        }

        $this->em->persist($restaurant);
        $this->em->flush();

        return $this->redirectToRoute('admin_super_plans');
    }

    #[Route('/restaurants/{id}/update', name: 'admin_super_restaurant_update', methods: ['POST'])]
    public function updateRestaurant(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_restaurant_update_' . $id);

        $restaurant = $this->restaurantRepository->find($id);
        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found.');
        }

        $restaurant->setName(trim($request->request->getString('name')));
        $restaurant->setEmail(trim($request->request->getString('email')) ?: null);
        $restaurant->setIsActive($request->request->getBoolean('is_active'));
        $restaurant->setSlug($this->buildUniqueRestaurantSlug($restaurant->getName(), $restaurant->getId()));

        $account = $this->resolveAccountForRestaurant($request);
        $restaurant->setCustomerAccount($account);

        $this->em->flush();

        return $this->redirectToRoute('admin_super_plans');
    }

    #[Route('/restaurants/{id}/delete', name: 'admin_super_restaurant_delete', methods: ['POST'])]
    public function deleteRestaurant(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_restaurant_delete_' . $id);

        $restaurant = $this->restaurantRepository->find($id);
        if ($restaurant) {
            $this->em->remove($restaurant);
            $this->em->flush();
        }

        return $this->redirectToRoute('admin_super_plans');
    }

    #[Route('/blog/create', name: 'admin_super_blog_create', methods: ['POST'])]
    public function createBlog(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_blog_create');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $title = trim($request->request->getString('title'));
        $slug = $this->buildUniqueBlogSlug($title);

        $post = new BlogPost(
            title: $title,
            slug: $slug,
            body: trim($request->request->getString('body')),
        );
        $post->setMetaTitle(trim($request->request->getString('meta_title')) ?: null);
        $post->setMetaDescription(trim($request->request->getString('meta_description')) ?: null);
        $post->setIsPublished($request->request->getBoolean('is_published'));

        $this->em->persist($post);
        $this->em->flush();
        $postId = (int) $post->getId();
        if ($postId > 0) {
            $this->translationService->upsert('blog_post', $postId, $locale, 'title', $title);
            $this->translationService->upsert('blog_post', $postId, $locale, 'body', trim($request->request->getString('body')));
            $this->translationService->upsert('blog_post', $postId, $locale, 'meta_title', trim($request->request->getString('meta_title')) ?: null);
            $this->translationService->upsert('blog_post', $postId, $locale, 'meta_description', trim($request->request->getString('meta_description')) ?: null);
            $this->em->flush();
        }

        return $this->redirectToRoute('admin_super_blog_index', ['lang' => $locale]);
    }

    #[Route('/blog/{id}/update', name: 'admin_super_blog_update', methods: ['POST'])]
    public function updateBlog(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_blog_update_' . $id);
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $post = $this->blogPostRepository->find($id);
        if (!$post) {
            throw $this->createNotFoundException('Blog post not found.');
        }

        if ($locale === 'tr') {
            $post->setTitle(trim($request->request->getString('title')));
            $post->setSlug($this->buildUniqueBlogSlug($post->getTitle(), $post->getId()));
            $post->setBody(trim($request->request->getString('body')));
            $post->setMetaTitle(trim($request->request->getString('meta_title')) ?: null);
            $post->setMetaDescription(trim($request->request->getString('meta_description')) ?: null);
        }
        $post->setIsPublished($request->request->getBoolean('is_published'));
        $post->setUpdatedAt(new \DateTimeImmutable());
        $postId = (int) $post->getId();
        if ($postId > 0) {
            $this->translationService->upsert('blog_post', $postId, $locale, 'title', trim($request->request->getString('title')));
            $this->translationService->upsert('blog_post', $postId, $locale, 'body', trim($request->request->getString('body')));
            $this->translationService->upsert('blog_post', $postId, $locale, 'meta_title', trim($request->request->getString('meta_title')) ?: null);
            $this->translationService->upsert('blog_post', $postId, $locale, 'meta_description', trim($request->request->getString('meta_description')) ?: null);
        }

        $this->em->flush();

        return $this->redirectToRoute('admin_super_blog_index', ['lang' => $locale]);
    }

    #[Route('/blog/{id}/delete', name: 'admin_super_blog_delete', methods: ['POST'])]
    public function deleteBlog(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_blog_delete_' . $id);
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $post = $this->blogPostRepository->find($id);
        if ($post) {
            $this->em->remove($post);
            $this->em->flush();
        }

        return $this->redirectToRoute('admin_super_blog_index', ['lang' => $locale]);
    }

    #[Route('/blog/comments/{id}/approve', name: 'admin_super_blog_comment_approve', methods: ['POST'])]
    public function approveBlogComment(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_blog_comment_approve_' . $id);
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $comment = $this->blogCommentRepository->find($id);
        if (!$comment instanceof BlogComment) {
            throw $this->createNotFoundException('Yorum bulunamadi.');
        }

        $comment->setIsPublished(true);
        $this->em->flush();
        $this->addFlash('success', 'Yorum onaylandi.');

        return $this->redirectToRoute('admin_super_blog_comments', ['lang' => $locale]);
    }

    #[Route('/blog/comments/{id}/reject', name: 'admin_super_blog_comment_reject', methods: ['POST'])]
    public function rejectBlogComment(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_blog_comment_reject_' . $id);
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $comment = $this->blogCommentRepository->find($id);
        if (!$comment instanceof BlogComment) {
            throw $this->createNotFoundException('Yorum bulunamadi.');
        }

        $comment->setIsPublished(false);
        $this->em->flush();
        $this->addFlash('success', 'Yorum onaydan kaldirildi.');

        return $this->redirectToRoute('admin_super_blog_comments', ['lang' => $locale]);
    }

    #[Route('/blog/comments/{id}/delete', name: 'admin_super_blog_comment_delete', methods: ['POST'])]
    public function deleteBlogComment(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_blog_comment_delete_' . $id);
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $comment = $this->blogCommentRepository->find($id);
        if ($comment instanceof BlogComment) {
            $this->em->remove($comment);
            $this->em->flush();
            $this->addFlash('success', 'Yorum silindi.');
        }

        return $this->redirectToRoute('admin_super_blog_comments', ['lang' => $locale]);
    }

    #[Route('/cms/update', name: 'admin_super_cms_update', methods: ['POST'])]
    public function updateCms(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_cms_update');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        foreach ($this->homeCmsFieldConfig() as $key => $definition) {
            $value = trim($request->request->getString($key));
            $content = $this->siteContentRepository->findOneBy(['keyName' => $key]);

            if (!$content instanceof SiteContent) {
                $content = new SiteContent(
                    keyName: $key,
                    title: $definition['title'],
                    body: $value,
                );
                $content->setIsPublished(true);
                $this->em->persist($content);
                $this->em->flush();
                $contentId = (int) $content->getId();
                if ($contentId > 0) {
                    $this->translationService->upsert('site_content', $contentId, $locale, 'body', $value);
                }
                continue;
            }

            $content->setTitle($definition['title']);
            if ($locale === 'tr') {
                $content->setBody($value);
            }
            $content->setIsPublished(true);
            $content->setUpdatedAt(new \DateTimeImmutable());

            $contentId = (int) $content->getId();
            if ($contentId > 0) {
                $this->translationService->upsert('site_content', $contentId, $locale, 'body', $value);
            }
        }

        $this->em->flush();
        $this->addFlash('success', 'Ana sayfa CMS alanlari guncellendi.');

        return $this->redirectToRoute('admin_super_cms', ['lang' => $locale]);
    }

    #[Route('/slider/create', name: 'admin_super_slider_create', methods: ['POST'])]
    public function createSlider(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_slider_create');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $keyName = trim($request->request->getString('key_name'));
        $title = trim($request->request->getString('title'));
        $description = trim($request->request->getString('description'));

        if ($keyName === '' || $title === '' || $description === '') {
            $this->addFlash('error', 'Slider key, baslik ve aciklama zorunludur.');
            return $this->redirectToRoute('admin_super_slider', ['lang' => $locale]);
        }
        if ($this->siteContentRepository->findOneBy(['keyName' => $keyName]) instanceof SiteContent) {
            $this->addFlash('error', 'Bu key ile bir slider icerigi zaten mevcut.');
            return $this->redirectToRoute('admin_super_slider', ['lang' => $locale]);
        }

        $content = new SiteContent(
            keyName: $keyName,
            title: $title,
            body: $this->buildSliderBodyJson($request, $description),
        );
        $content->setIsPublished($request->request->getBoolean('is_published'));

        $this->em->persist($content);
        $this->em->flush();
        $contentId = (int) $content->getId();
        if ($contentId > 0) {
            $this->translationService->upsert('site_content', $contentId, $locale, 'title', $title);
            $this->translationService->upsert('site_content', $contentId, $locale, 'slider_description', $description);
            $this->translationService->upsert('site_content', $contentId, $locale, 'slider_kicker', trim($request->request->getString('kicker')));
            $this->translationService->upsert('site_content', $contentId, $locale, 'slider_note', trim($request->request->getString('note')));
            $this->em->flush();
        }

        $this->addFlash('success', 'Slider maddesi eklendi.');

        return $this->redirectToRoute('admin_super_slider', ['lang' => $locale]);
    }

    #[Route('/slider/{id}/update', name: 'admin_super_slider_update', methods: ['POST'])]
    public function updateSlider(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_slider_update_' . $id);
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $content = $this->siteContentRepository->find($id);
        if (!$content instanceof SiteContent) {
            throw $this->createNotFoundException('Slider icerigi bulunamadi.');
        }

        $title = trim($request->request->getString('title'));
        $description = trim($request->request->getString('description'));
        if ($title === '' || $description === '') {
            $this->addFlash('error', 'Baslik ve aciklama bos birakilamaz.');
            return $this->redirectToRoute('admin_super_slider', ['lang' => $locale]);
        }

        if ($locale === 'tr') {
            $content->setTitle($title);
            $content->setBody($this->buildSliderBodyJson($request, $description));
        }
        $content->setIsPublished($request->request->getBoolean('is_published'));
        $content->setUpdatedAt(new \DateTimeImmutable());
        $contentId = (int) $content->getId();
        if ($contentId > 0) {
            $this->translationService->upsert('site_content', $contentId, $locale, 'title', $title);
            $this->translationService->upsert('site_content', $contentId, $locale, 'slider_description', $description);
            $this->translationService->upsert('site_content', $contentId, $locale, 'slider_kicker', trim($request->request->getString('kicker')));
            $this->translationService->upsert('site_content', $contentId, $locale, 'slider_note', trim($request->request->getString('note')));
        }

        $this->em->flush();

        $this->addFlash('success', 'Slider maddesi guncellendi.');

        return $this->redirectToRoute('admin_super_slider', ['lang' => $locale]);
    }

    #[Route('/contents/create', name: 'admin_super_content_create', methods: ['POST'])]
    public function createContent(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_content_create');
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $content = new SiteContent(
            keyName: trim($request->request->getString('key_name')),
            title: trim($request->request->getString('title')),
            body: trim($request->request->getString('body')),
        );
        $content->setIsPublished($request->request->getBoolean('is_published'));

        $this->em->persist($content);
        $this->em->flush();

        return $this->redirectToRoute($this->resolveContentRedirectRoute($request), ['lang' => $locale]);
    }

    #[Route('/contents/{id}/update', name: 'admin_super_content_update', methods: ['POST'])]
    public function updateContent(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_content_update_' . $id);
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $content = $this->siteContentRepository->find($id);
        if (!$content) {
            throw $this->createNotFoundException('Site content not found.');
        }

        $content->setKeyName(trim($request->request->getString('key_name')));
        $content->setTitle(trim($request->request->getString('title')));
        $content->setBody(trim($request->request->getString('body')));
        $content->setIsPublished($request->request->getBoolean('is_published'));
        $content->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $this->redirectToRoute($this->resolveContentRedirectRoute($request), ['lang' => $locale]);
    }

    #[Route('/contents/{id}/delete', name: 'admin_super_content_delete', methods: ['POST'])]
    public function deleteContent(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->validateCsrfOrFail($request, 'super_content_delete_' . $id);
        $locale = $this->languageContext->resolveAdminLocale($request, 'tr');

        $content = $this->siteContentRepository->find($id);
        if ($content) {
            $this->em->remove($content);
            $this->em->flush();
        }

        return $this->redirectToRoute($this->resolveContentRedirectRoute($request), ['lang' => $locale]);
    }

    private function validateCsrfOrFail(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function buildUniqueRestaurantSlug(string $name, ?int $excludeId = null): string
    {
        $slugger = new AsciiSlugger();
        $base = strtolower((string) $slugger->slug($name));
        $base = $base !== '' ? $base : 'restaurant';

        $attempt = $base;
        $i = 1;
        while (true) {
            $existing = $this->restaurantRepository->findBySlug($attempt);
            if ($existing === null || ($excludeId !== null && $existing->getId() === $excludeId)) {
                return $attempt;
            }

            $attempt = sprintf('%s-%d', $base, $i);
            ++$i;
        }
    }

    private function buildUniqueBlogSlug(string $title, ?int $excludeId = null): string
    {
        $slugger = new AsciiSlugger();
        $base = strtolower((string) $slugger->slug($title));
        $base = $base !== '' ? $base : 'blog-post';

        $attempt = $base;
        $i = 1;
        while (true) {
            $existing = $this->blogPostRepository->findOneBy(['slug' => $attempt]);
            if ($existing === null || ($excludeId !== null && $existing->getId() === $excludeId)) {
                return $attempt;
            }

            $attempt = sprintf('%s-%d', $base, $i);
            ++$i;
        }
    }

    private function resolveAccountForRestaurant(Request $request): ?CustomerAccount
    {
        $accountId = $request->request->getString('customer_account_id');
        if ($accountId !== '') {
            return $this->customerAccountRepository->find((int) $accountId);
        }

        $accountName = trim($request->request->getString('customer_account_name'));
        if ($accountName === '') {
            return null;
        }

        $existing = $this->customerAccountRepository->findOneBy(['name' => $accountName]);
        if ($existing instanceof CustomerAccount) {
            return $existing;
        }

        $account = new CustomerAccount($accountName);
        $account->setEmail(trim($request->request->getString('customer_account_email')) ?: null);
        $this->em->persist($account);

        return $account;
    }

    private function parseDateOrNull(string $value): ?\DateTimeImmutable
    {
        $normalized = trim($value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $normalized);
        if (!$date instanceof \DateTimeImmutable) {
            return null;
        }

        $errors = \DateTimeImmutable::getLastErrors();
        if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            return null;
        }

        if ($date->format('Y-m-d') !== $normalized) {
            return null;
        }

        return $date;
    }

    /**
     * @param list<SiteContent> $sliderContents
     */
    private function buildNextSliderKey(array $sliderContents): string
    {
        $maxIndex = 0;

        foreach ($sliderContents as $content) {
            if (preg_match('/^home_slider_(\d+)$/', $content->getKeyName(), $matches) !== 1) {
                continue;
            }

            $value = (int) $matches[1];
            if ($value > $maxIndex) {
                $maxIndex = $value;
            }
        }

        return sprintf('home_slider_%d', $maxIndex + 1);
    }

    private function resolveContentRedirectRoute(Request $request): string
    {
        $route = $request->request->getString('_redirect');
        $allowed = ['admin_super_plans', 'admin_super_slider', 'admin_super_cms'];

        return in_array($route, $allowed, true) ? $route : 'admin_super_plans';
    }

    /**
     * @return array{lang: string, pool?: string, q?: string}
     */
    private function buildCacheRedirectParams(Request $request, string $locale): array
    {
        $params = ['lang' => $locale];

        $pool = trim($request->request->getString('pool', $request->query->getString('pool')));
        if ($pool !== '') {
            $params['pool'] = $pool;
        }

        $query = trim($request->request->getString('q', $request->query->getString('q')));
        if ($query !== '') {
            $params['q'] = $query;
        }

        return $params;
    }

    /**
     * @return array<string, array{label: string, title: string, placeholder: string}>
     */
    private function homeCmsFieldConfig(): array
    {
        return [
            'home_header_brand' => [
                'label' => 'Header marka adi',
                'title' => 'Header Brand',
                'placeholder' => 'Orn: Altay QR Menu',
            ],
            'home_header_cta_text' => [
                'label' => 'Header buton metni',
                'title' => 'Header CTA Text',
                'placeholder' => 'Orn: Panel Girisi',
            ],
            'home_header_cta_url' => [
                'label' => 'Header buton URL',
                'title' => 'Header CTA URL',
                'placeholder' => 'Orn: /admin/login',
            ],
            'home_hero_eyebrow' => [
                'label' => 'Hero ust etiket',
                'title' => 'Hero Eyebrow',
                'placeholder' => 'Orn: Kurumsal QR Menu Altyapisi',
            ],
            'home_hero_title' => [
                'label' => 'Hero baslik',
                'title' => 'Hero Title',
                'placeholder' => 'Anasayfa buyuk baslik metni',
            ],
            'home_hero_description' => [
                'label' => 'Hero aciklama',
                'title' => 'Hero Description',
                'placeholder' => 'Anasayfa aciklama metni',
            ],
            'home_hero_primary_text' => [
                'label' => 'Hero birincil buton metni',
                'title' => 'Hero Primary Text',
                'placeholder' => 'Orn: Planlari Incele',
            ],
            'home_hero_primary_url' => [
                'label' => 'Hero birincil buton URL',
                'title' => 'Hero Primary URL',
                'placeholder' => 'Orn: #plans',
            ],
            'home_hero_secondary_text' => [
                'label' => 'Hero ikincil buton metni',
                'title' => 'Hero Secondary Text',
                'placeholder' => 'Orn: Tum Ozellikleri Gor',
            ],
            'home_hero_secondary_url' => [
                'label' => 'Hero ikincil buton URL',
                'title' => 'Hero Secondary URL',
                'placeholder' => 'Orn: #ozellikler',
            ],
            'home_contact_title' => [
                'label' => 'Iletisim kart basligi',
                'title' => 'Contact Title',
                'placeholder' => 'Orn: Satis ve Destek',
            ],
            'home_contact_note' => [
                'label' => 'Iletisim aciklama',
                'title' => 'Contact Note',
                'placeholder' => 'Iletisim bolumu aciklamasi',
            ],
            'home_contact_email' => [
                'label' => 'Iletisim e-posta',
                'title' => 'Contact Email',
                'placeholder' => 'Orn: iletisim@altayqrmenu.com',
            ],
            'home_contact_phone' => [
                'label' => 'Iletisim telefon',
                'title' => 'Contact Phone',
                'placeholder' => 'Orn: +90 850 000 00 00',
            ],
            'home_footer_text' => [
                'label' => 'Footer metni',
                'title' => 'Footer Text',
                'placeholder' => 'Orn: Altay QR Menu - tum haklari saklidir.',
            ],
        ];
    }

    /**
     * @param array{fields: array<string, array{label: string, type: string, placeholder?: string}>, jsonFields: list<string>} $definition
     * @return array<string, mixed>
     */
    private function buildWidgetConfig(Request $request, array $definition): array
    {
        $config = [];
        foreach ($definition['fields'] as $key => $field) {
            $value = trim($request->request->getString('field_' . $key));
            if ($value === '') {
                $config[$key] = '';
                continue;
            }
            if ($field['type'] === 'json') {
                $decoded = json_decode($value, true);
                $config[$key] = is_array($decoded) ? $decoded : [];
                continue;
            }
            $config[$key] = $value;
        }

        return $config;
    }

    /**
     * @param array{fields: array<string, array{label: string, type: string, placeholder?: string}>} $definition
     */
    private function validateWidgetJsonFields(Request $request, array $definition): ?string
    {
        foreach ($definition['fields'] as $key => $field) {
            if ($field['type'] !== 'json') {
                continue;
            }
            $value = trim($request->request->getString('field_' . $key));
            if ($value === '') {
                continue;
            }
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                return sprintf('"%s" alani gecerli bir JSON olmalidir.', $field['label']);
            }
        }

        return null;
    }

    /**
     * @param array{translatable: list<string>} $definition
     */
    private function upsertWidgetTranslations(\App\Entity\SiteWidget $widget, array $definition, string $locale, Request $request): void
    {
        $widgetId = (int) $widget->getId();
        if ($widgetId <= 0) {
            return;
        }

        foreach ($definition['translatable'] as $field) {
            $value = trim($request->request->getString('field_' . $field));
            $this->translationService->upsert('site_widget', $widgetId, $locale, $field, $value);
        }
    }

    private function buildSliderBodyJson(Request $request, string $description): string
    {
        return (string) json_encode([
            'description' => $description,
            'imageUrl' => trim($request->request->getString('image_url')),
            'kicker' => trim($request->request->getString('kicker')),
            'note' => trim($request->request->getString('note')),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<int, array<string, string>> $translations
     * @return array{id: int|null, keyName: string, title: string, description: string, imageUrl: string, kicker: string, note: string, isPublished: bool}
     */
    private function mapSliderContent(SiteContent $content, array $translations): array
    {
        $payload = json_decode($content->getBody(), true);

        if (!is_array($payload)) {
            $payload = ['description' => $content->getBody()];
        }

        $contentId = (int) $content->getId();
        $translatedTitle = $translations[$contentId]['title'] ?? null;
        $translatedDescription = $translations[$contentId]['slider_description'] ?? null;
        $translatedKicker = $translations[$contentId]['slider_kicker'] ?? null;
        $translatedNote = $translations[$contentId]['slider_note'] ?? null;

        return [
            'id' => $content->getId(),
            'keyName' => $content->getKeyName(),
            'title' => is_string($translatedTitle) && trim($translatedTitle) !== '' ? $translatedTitle : $content->getTitle(),
            'description' => is_string($translatedDescription) && trim($translatedDescription) !== '' ? $translatedDescription : (string) ($payload['description'] ?? $content->getBody()),
            'imageUrl' => (string) ($payload['imageUrl'] ?? ''),
            'kicker' => is_string($translatedKicker) && trim($translatedKicker) !== '' ? $translatedKicker : (string) ($payload['kicker'] ?? ''),
            'note' => is_string($translatedNote) && trim($translatedNote) !== '' ? $translatedNote : (string) ($payload['note'] ?? ''),
            'isPublished' => $content->isPublished(),
        ];
    }

    private function generateTemporaryPassword(): string
    {
        return bin2hex(random_bytes(6));
    }

    private function sendWelcomePasswordEmail(string $to, string $accountName, string $temporaryPassword): bool
    {
        $loginUrl = $this->generateUrl('admin_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $subject = 'QR Menu hesap bilgileri';
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "From: noreply@qrmenu.local\r\n";

        $body = sprintf(
            "Merhaba %s,\n\nQR Menu aboneliginiz olusturuldu.\n\nGiris e-postasi: %s\nGecici sifre: %s\n\nGiris adresi: %s\n\nIlk giristen sonra sifrenizi degistirmenizi oneririz.",
            $accountName,
            $to,
            $temporaryPassword,
            $loginUrl,
        );

        return mail($to, $subject, $body, $headers);
    }
}
