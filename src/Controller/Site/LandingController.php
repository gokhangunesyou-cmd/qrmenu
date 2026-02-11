<?php

namespace App\Controller\Site;

use App\Entity\SiteContent;
use App\Repository\BlogPostRepository;
use App\Repository\PlanRepository;
use App\Repository\SiteContentRepository;
use App\Service\LanguageContext;
use App\Service\TranslationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LandingController extends AbstractController
{
    #[Route('/', name: 'site_home', methods: ['GET'])]
    public function index(
        Request $request,
        PlanRepository $planRepository,
        BlogPostRepository $blogPostRepository,
        SiteContentRepository $siteContentRepository,
        LanguageContext $languageContext,
        TranslationService $translationService,
    ): Response
    {
        $locale = $languageContext->resolveSiteLocale($request, 'tr');
        $plans = $planRepository->findActiveOrdered();
        $posts = $blogPostRepository->findLatestPublished(3);
        $postIds = array_values(array_map(static fn ($post): int => (int) $post->getId(), $posts));
        $postTranslations = $translationService->getFieldMapWithFallback('blog_post', $postIds, $locale);
        $postView = [];
        foreach ($posts as $post) {
            $postId = (int) $post->getId();
            $postView[$postId] = [
                'title' => $translationService->resolve($postTranslations, $postId, 'title', $post->getTitle()) ?? $post->getTitle(),
                'body' => $translationService->resolve($postTranslations, $postId, 'body', $post->getBody()) ?? $post->getBody(),
                'metaDescription' => $translationService->resolve($postTranslations, $postId, 'meta_description', $post->getMetaDescription()),
            ];
        }
        $sliderContents = $siteContentRepository->findPublishedByPrefix('home_slider_');
        $publishedHomeContents = $siteContentRepository->findPublishedByPrefix('home_');
        $contentIds = array_values(array_map(static fn (SiteContent $content): int => (int) $content->getId(), $publishedHomeContents));
        $translations = $translationService->getFieldMapWithFallback('site_content', $contentIds, $locale);

        $sliderItems = [];
        foreach ($sliderContents as $index => $content) {
            $payload = $this->decodeJsonPayload($content->getBody());
            $contentId = (int) $content->getId();
            $sliderItems[] = [
                'title' => $translationService->resolve($translations, $contentId, 'title', $content->getTitle()) ?? $content->getTitle(),
                'body' => $translationService->resolve($translations, $contentId, 'slider_description', (string) ($payload['description'] ?? $content->getBody())) ?? (string) ($payload['description'] ?? $content->getBody()),
                'imageUrl' => (string) ($payload['imageUrl'] ?? ''),
                'kicker' => $translationService->resolve($translations, $contentId, 'slider_kicker', (string) ($payload['kicker'] ?? sprintf('Slider %02d', $index + 1))) ?? (string) ($payload['kicker'] ?? sprintf('Slider %02d', $index + 1)),
                'note' => $translationService->resolve($translations, $contentId, 'slider_note', (string) ($payload['note'] ?? 'Bu icerigi guncellemek icin: Super Admin > Slider menusu.')) ?? (string) ($payload['note'] ?? 'Bu icerigi guncellemek icin: Super Admin > Slider menusu.'),
            ];
        }

        $contentMap = [];
        foreach ($publishedHomeContents as $content) {
            if (str_starts_with($content->getKeyName(), 'home_slider_')) {
                continue;
            }
            $contentId = (int) $content->getId();
            $contentMap[$content->getKeyName()] = $translationService->resolve($translations, $contentId, 'body', $content->getBody()) ?? $content->getBody();
        }

        $cms = [
            'headerBrand' => $this->resolveContentValue($contentMap, 'home_header_brand', 'Altay QR Menu'),
            'headerCtaText' => $this->resolveContentValue($contentMap, 'home_header_cta_text', 'Panel Girisi'),
            'headerCtaUrl' => $this->resolveContentValue($contentMap, 'home_header_cta_url', $this->generateUrl('admin_login')),
            'heroEyebrow' => $this->resolveContentValue($contentMap, 'home_hero_eyebrow', 'Kurumsal QR Menu Altyapisi'),
            'heroTitle' => $this->resolveContentValue($contentMap, 'home_hero_title', 'Tek panelden menuyu yonetin, masaya ozel QR deneyimiyle servisi hizlandirin.'),
            'heroDescription' => $this->resolveContentValue($contentMap, 'home_hero_description', 'Altay QR Menu; restoranlar, zincir markalar ve franchise operasyonlari icin tasarlandi. Urun, kategori, masa, personel ve PDF cikti sureclerini tek akista yonetir.'),
            'heroPrimaryText' => $this->resolveContentValue($contentMap, 'home_hero_primary_text', 'Planlari Incele'),
            'heroPrimaryUrl' => $this->resolveContentValue($contentMap, 'home_hero_primary_url', '#plans'),
            'heroSecondaryText' => $this->resolveContentValue($contentMap, 'home_hero_secondary_text', 'Tum Ozellikleri Gor'),
            'heroSecondaryUrl' => $this->resolveContentValue($contentMap, 'home_hero_secondary_url', '#ozellikler'),
            'contactTitle' => $this->resolveContentValue($contentMap, 'home_contact_title', 'Satis ve Destek'),
            'contactNote' => $this->resolveContentValue($contentMap, 'home_contact_note', 'Satis demosu, fiyatlandirma ve onboarding sureci icin bize ulasin.'),
            'contactEmail' => $this->resolveContentValue($contentMap, 'home_contact_email', 'iletisim@altayqrmenu.com'),
            'contactPhone' => $this->resolveContentValue($contentMap, 'home_contact_phone', '+90 850 000 00 00'),
            'footerText' => $this->resolveContentValue($contentMap, 'home_footer_text', 'Altay QR Menu - tum haklari saklidir.'),
        ];

        return $this->render('site/home.html.twig', [
            'plans' => $plans,
            'posts' => $posts,
            'postView' => $postView,
            'sliderItems' => $sliderItems,
            'cms' => $cms,
            'currentLocale' => $locale,
            'availableLocales' => $languageContext->getLocaleLabelMap(),
        ]);
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

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonPayload(string $body): array
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
