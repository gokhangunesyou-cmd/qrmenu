<?php

namespace App\Fixtures;

use App\Entity\Category;
use App\Entity\Media;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\Restaurant;
use App\Entity\Translation;
use App\Enum\ProductStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

class CategoryProductFixtures extends Fixture implements DependentFixtureInterface
{
    private const CATEGORY_COLORS = [
        'Kahvaltı' => ['#F59E0B', '#F97316'],
        'Çorbalar' => ['#FB7185', '#EF4444'],
        'Mezeler' => ['#14B8A6', '#0EA5E9'],
        'Salatalar' => ['#22C55E', '#16A34A'],
        'Ana Yemekler' => ['#A855F7', '#7C3AED'],
        'Izgaralar' => ['#F97316', '#DC2626'],
        'Pideler' => ['#F59E0B', '#EA580C'],
        'Kebaplar' => ['#EF4444', '#B91C1C'],
        'Tatlılar' => ['#EC4899', '#DB2777'],
        'İçecekler' => ['#0EA5E9', '#2563EB'],
    ];

    public function load(ObjectManager $manager): void
    {
        $restaurant = $manager->getRepository(Restaurant::class)->findOneBy(['slug' => 'demo-restoran']);
        if ($restaurant === null) {
            return;
        }

        $categories = [
            ['name' => 'Kahvaltı', 'sort' => 1],
            ['name' => 'Çorbalar', 'sort' => 2],
            ['name' => 'Mezeler', 'sort' => 3],
            ['name' => 'Salatalar', 'sort' => 4],
            ['name' => 'Ana Yemekler', 'sort' => 5],
            ['name' => 'Izgaralar', 'sort' => 6],
            ['name' => 'Pideler', 'sort' => 7],
            ['name' => 'Kebaplar', 'sort' => 8],
            ['name' => 'Tatlılar', 'sort' => 9],
            ['name' => 'İçecekler', 'sort' => 10],
        ];

        $categoryMap = [];
        foreach ($categories as $data) {
            $category = $manager->getRepository(Category::class)->findOneBy([
                'restaurant' => $restaurant,
                'name' => $data['name'],
            ]);
            if ($category === null) {
                $category = new Category($data['name'], $restaurant);
                $category->setSortOrder($data['sort']);
                $manager->persist($category);
            }
            $categoryMap[$data['name']] = $category;
        }

        $products = [
            'Kahvaltı' => [
                ['Serpme Kahvaltı', '290.00', 1, true],
                ['Menemen', '120.00', 2, false],
                ['Sucuklu Yumurta', '135.00', 3, false],
                ['Beyaz Peynir Tabağı', '95.00', 4, false],
            ],
            'Çorbalar' => [
                ['Mercimek Çorbası', '60.00', 1, false],
                ['Ezogelin Çorbası', '65.00', 2, false],
                ['Tavuk Suyu Çorba', '75.00', 3, false],
            ],
            'Mezeler' => [
                ['Humus', '85.00', 1, false],
                ['Haydari', '80.00', 2, false],
                ['Acılı Ezme', '75.00', 3, false],
                ['Şakşuka', '90.00', 4, false],
            ],
            'Salatalar' => [
                ['Çoban Salata', '70.00', 1, false],
                ['Mevsim Salata', '75.00', 2, false],
                ['Gavurdağı Salata', '95.00', 3, false],
            ],
            'Ana Yemekler' => [
                ['Etli Güveç', '220.00', 1, false],
                ['Kuru Fasulye & Pilav', '165.00', 2, false],
                ['Tavuk Sote', '190.00', 3, false],
            ],
            'Izgaralar' => [
                ['Tavuk Şiş', '210.00', 1, false],
                ['Köfte', '200.00', 2, false],
                ['Izgara Antrikot', '360.00', 3, false],
            ],
            'Pideler' => [
                ['Kıymalı Pide', '160.00', 1, false],
                ['Kuşbaşılı Pide', '175.00', 2, false],
                ['Kaşarlı Pide', '150.00', 3, false],
            ],
            'Kebaplar' => [
                ['Adana Kebap', '240.00', 1, false],
                ['Urfa Kebap', '240.00', 2, false],
                ['Beyti Sarma', '275.00', 3, false],
            ],
            'Tatlılar' => [
                ['Künefe', '130.00', 1, false],
                ['Fırın Sütlaç', '110.00', 2, false],
                ['Baklava', '140.00', 3, false],
            ],
            'İçecekler' => [
                ['Ayran', '35.00', 1, false],
                ['Çay', '25.00', 2, false],
                ['Türk Kahvesi', '55.00', 3, false],
                ['Gazlı İçecek', '45.00', 4, false],
            ],
        ];

        foreach ($products as $categoryName => $items) {
            $category = $categoryMap[$categoryName] ?? null;
            if ($category === null) {
                continue;
            }

            foreach ($items as [$name, $price, $sortOrder, $featured]) {
                $product = $manager->getRepository(Product::class)->findOneBy([
                    'restaurant' => $restaurant,
                    'category' => $category,
                    'name' => $name,
                ]);

                if ($product === null) {
                    $product = new Product($name, $price, $category, $restaurant);
                    $product->setSortOrder($sortOrder);
                    $product->setStatus(ProductStatus::APPROVED);
                    $product->setSubmittedAt(new \DateTimeImmutable());
                    $product->setIsFeatured($featured);
                    $manager->persist($product);
                }

                $this->attachProductImageIfMissing($manager, $product, $restaurant, $categoryName, $name);
            }
        }

        $manager->flush();

        foreach ($categoryMap as $category) {
            $this->seedNameTranslations($manager, 'category', (int) $category->getId(), $category->getName());
        }

        $allProducts = $manager->getRepository(Product::class)->findBy(['restaurant' => $restaurant]);
        foreach ($allProducts as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $this->seedNameTranslations($manager, 'product', (int) $product->getId(), $product->getName());
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            SuperAdminFixtures::class,
        ];
    }

    private function attachProductImageIfMissing(
        ObjectManager $manager,
        Product $product,
        Restaurant $restaurant,
        string $categoryName,
        string $productName,
    ): void {
        if (count($product->getImages()) > 0) {
            return;
        }

        $generated = $this->generateProductImage($categoryName, $productName);
        $content = $generated['content'];
        $mimeType = $generated['mime'];
        $extension = $generated['extension'];

        $mediaUuid = Uuid::uuid7()->toString();
        $restaurantUuid = $restaurant->getUuid()->toString();
        $storagePath = sprintf('media/%s/%s.%s', $restaurantUuid, $mediaUuid, $extension);
        $fullPath = sprintf('%s/public/uploads/%s', dirname(__DIR__), $storagePath);

        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($fullPath, $content);

        // Product list first tries thumbs path, then falls back to original.
        $thumbPath = sprintf('%s/public/uploads/media/%s/thumbs/%s.%s', dirname(__DIR__), $restaurantUuid, $mediaUuid, $extension);
        $thumbDir = dirname($thumbPath);
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0775, true);
        }
        file_put_contents($thumbPath, $content);

        $media = new Media(
            sprintf('%s.%s', $this->slugify($productName), $extension),
            $storagePath,
            $mimeType,
            strlen($content),
            $restaurant,
        );
        $media->setWidth($generated['width']);
        $media->setHeight($generated['height']);
        $media->setAltText($productName);
        $manager->persist($media);

        $productImage = new ProductImage($product, $media, 0);
        $product->addImage($productImage);
        $manager->persist($productImage);
    }

    /**
     * @return array{content: string, mime: string, extension: string, width: int, height: int}
     */
    private function generateProductImage(string $categoryName, string $productName): array
    {
        $width = 1200;
        $height = 800;
        [$startColor, $endColor] = self::CATEGORY_COLORS[$categoryName] ?? ['#334155', '#0F172A'];
        $title = $this->escapeSvgText($productName);
        $subtitle = $this->escapeSvgText($categoryName);

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$startColor}" />
      <stop offset="100%" stop-color="{$endColor}" />
    </linearGradient>
  </defs>
  <rect width="100%" height="100%" fill="url(#bg)" />
  <rect x="64" y="64" width="1072" height="672" rx="36" fill="rgba(15,23,42,0.28)" />
  <text x="96" y="370" fill="#FFFFFF" font-size="78" font-family="Arial, sans-serif" font-weight="700">{$title}</text>
  <text x="96" y="440" fill="#E2E8F0" font-size="40" font-family="Arial, sans-serif">{$subtitle}</text>
  <text x="96" y="700" fill="#F8FAFC" font-size="28" font-family="Arial, sans-serif">QR MENU DEMO</text>
</svg>
SVG;

        return [
            'content' => $svg,
            'mime' => 'image/svg+xml',
            'extension' => 'svg',
            'width' => $width,
            'height' => $height,
        ];
    }

    private function slugify(string $value): string
    {
        $normalized = strtolower($value);
        $normalized = strtr($normalized, [
            'ı' => 'i',
            'İ' => 'i',
            'ğ' => 'g',
            'Ğ' => 'g',
            'ü' => 'u',
            'Ü' => 'u',
            'ş' => 's',
            'Ş' => 's',
            'ö' => 'o',
            'Ö' => 'o',
            'ç' => 'c',
            'Ç' => 'c',
            '&' => 'and',
        ]);
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'product';
    }

    private function escapeSvgText(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function seedNameTranslations(ObjectManager $manager, string $entityType, int $entityId, string $name): void
    {
        if ($entityId <= 0) {
            return;
        }

        foreach (['en', 'es', 'ar', 'hi', 'zh'] as $locale) {
            $existing = $manager->getRepository(Translation::class)->findOneBy([
                'entityType' => $entityType,
                'entityId' => $entityId,
                'locale' => $locale,
                'field' => 'name',
            ]);

            if ($existing instanceof Translation) {
                $existing->setValue($name);
                continue;
            }

            $manager->persist(new Translation($entityType, $entityId, $locale, 'name', $name));
        }
    }
}
