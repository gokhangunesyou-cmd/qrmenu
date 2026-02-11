<?php

namespace App\Controller\Site;

use App\Repository\BlogPostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SeoController extends AbstractController
{
    #[Route('/robots.txt', name: 'site_robots', methods: ['GET'])]
    public function robots(Request $request): Response
    {
        $content = "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Disallow: /admin\n";
        $content .= "Sitemap: " . $request->getSchemeAndHttpHost() . $this->generateUrl('site_sitemap') . "\n";

        return new Response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    #[Route('/sitemap.xml', name: 'site_sitemap', methods: ['GET'])]
    public function sitemap(BlogPostRepository $blogPostRepository): Response
    {
        $urls = [
            ['loc' => $this->generateUrl('site_home', [], UrlGeneratorInterface::ABSOLUTE_URL), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => $this->generateUrl('site_blog_index', [], UrlGeneratorInterface::ABSOLUTE_URL), 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => $this->generateUrl('site_signup', [], UrlGeneratorInterface::ABSOLUTE_URL), 'changefreq' => 'weekly', 'priority' => '0.8'],
        ];

        foreach ($blogPostRepository->findLatestPublished(200) as $post) {
            $urls[] = [
                'loc' => $this->generateUrl('site_blog_detail', ['slug' => $post->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => $post->getUpdatedAt()->format('Y-m-d'),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($url['loc'], ENT_XML1) . "</loc>\n";
            if (isset($url['lastmod'])) {
                $xml .= "    <lastmod>{$url['lastmod']}</lastmod>\n";
            }
            $xml .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$url['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>";

        return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
