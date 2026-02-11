<?php

namespace App\Controller\Site;

use App\Entity\BlogComment;
use App\Repository\BlogCommentRepository;
use App\Repository\BlogPostRepository;
use App\Repository\SiteContentRepository;
use App\Service\LanguageContext;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/blog')]
class BlogController extends AbstractController
{
    #[Route('', name: 'site_blog_index', methods: ['GET'])]
    public function index(
        Request $request,
        BlogPostRepository $blogPostRepository,
        SiteContentRepository $siteContentRepository,
        LanguageContext $languageContext,
        TranslationService $translationService,
    ): Response {
        $locale = $languageContext->resolveSiteLocale($request, 'tr');
        $q = trim($request->query->getString('q'));
        $posts = $q === ''
            ? $blogPostRepository->findLatestPublished(30)
            : $blogPostRepository->searchPublished($q, 30);

        $postIds = array_values(array_map(static fn ($post): int => (int) $post->getId(), $posts));
        $postTranslations = $translationService->getFieldMapWithFallback('blog_post', $postIds, $locale);

        $postView = [];
        foreach ($posts as $post) {
            $postId = (int) $post->getId();
            $postView[$postId] = [
                'title' => $translationService->resolve($postTranslations, $postId, 'title', $post->getTitle()) ?? $post->getTitle(),
                'body' => $translationService->resolve($postTranslations, $postId, 'body', $post->getBody()) ?? $post->getBody(),
                'metaTitle' => $translationService->resolve($postTranslations, $postId, 'meta_title', $post->getMetaTitle()),
                'metaDescription' => $translationService->resolve($postTranslations, $postId, 'meta_description', $post->getMetaDescription()),
            ];
        }

        return $this->render('site/blog/index.html.twig', [
            'posts' => $posts,
            'postView' => $postView,
            'q' => $q,
            'cms' => $this->buildSiteCms($siteContentRepository, $translationService, $locale),
            'currentLocale' => $locale,
            'availableLocales' => $languageContext->getLocaleLabelMap(),
        ]);
    }

    #[Route('/{slug}', name: 'site_blog_detail', methods: ['GET'])]
    public function detail(
        string $slug,
        Request $request,
        BlogPostRepository $blogPostRepository,
        BlogCommentRepository $blogCommentRepository,
        SiteContentRepository $siteContentRepository,
        EntityManagerInterface $entityManager,
        LanguageContext $languageContext,
        TranslationService $translationService,
    ): Response {
        $locale = $languageContext->resolveSiteLocale($request, 'tr');
        $post = $blogPostRepository->findPublishedBySlug($slug);
        if ($post === null) {
            throw $this->createNotFoundException('Blog yazisi bulunamadi.');
        }

        $post->incrementViewCount();
        $entityManager->flush();

        $postTranslations = $translationService->getFieldMapWithFallback('blog_post', [(int) $post->getId()], $locale);
        $postView = [
            'title' => $translationService->resolve($postTranslations, (int) $post->getId(), 'title', $post->getTitle()) ?? $post->getTitle(),
            'body' => $translationService->resolve($postTranslations, (int) $post->getId(), 'body', $post->getBody()) ?? $post->getBody(),
            'metaTitle' => $translationService->resolve($postTranslations, (int) $post->getId(), 'meta_title', $post->getMetaTitle()),
            'metaDescription' => $translationService->resolve($postTranslations, (int) $post->getId(), 'meta_description', $post->getMetaDescription()),
        ];

        return $this->render('site/blog/detail.html.twig', [
            'post' => $post,
            'postView' => $postView,
            'comments' => $blogCommentRepository->findPublishedForPost($post),
            'cms' => $this->buildSiteCms($siteContentRepository, $translationService, $locale),
            'currentLocale' => $locale,
            'availableLocales' => $languageContext->getLocaleLabelMap(),
        ]);
    }

    #[Route('/{slug}/comment', name: 'site_blog_comment_create', methods: ['POST'])]
    public function createComment(
        string $slug,
        Request $request,
        BlogPostRepository $blogPostRepository,
        EntityManagerInterface $entityManager,
        LanguageContext $languageContext,
    ): RedirectResponse {
        $locale = $languageContext->resolveSiteLocale($request, 'tr');
        $post = $blogPostRepository->findPublishedBySlug($slug);
        if ($post === null) {
            throw $this->createNotFoundException('Blog yazisi bulunamadi.');
        }

        if (!$this->isCsrfTokenValid('blog_comment_' . $post->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Gecersiz CSRF token.');
        }

        $authorName = trim($request->request->getString('author_name'));
        $body = trim($request->request->getString('body'));

        if ($authorName === '' || $body === '') {
            $this->addFlash('error', 'Ad ve yorum alani zorunludur.');

            return $this->redirectToRoute('site_blog_detail', ['slug' => $post->getSlug(), 'lang' => $locale]);
        }

        if (mb_strlen($authorName) > 120 || mb_strlen($body) > 3000) {
            $this->addFlash('error', 'Yorum cok uzun.');

            return $this->redirectToRoute('site_blog_detail', ['slug' => $post->getSlug(), 'lang' => $locale]);
        }

        $comment = new BlogComment($post, $authorName, $body);
        $comment->setIsPublished(false);
        $post->addComment($comment);

        $entityManager->persist($comment);
        $entityManager->flush();

        $this->addFlash('success', 'Yorumunuz alindi. Onay sonrasi yayinlanacaktir.');

        return $this->redirectToRoute('site_blog_detail', ['slug' => $post->getSlug(), 'lang' => $locale]);
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
