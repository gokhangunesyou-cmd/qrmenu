<?php

namespace App\Fixtures;

use App\Entity\Category;
use App\Entity\Media;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\Restaurant;
use App\Enum\ProductStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Ramsey\Uuid\Uuid;

class CategoryProductFixtures extends Fixture implements DependentFixtureInterface
{
    private const PRODUCT_IMAGE_KEYWORDS = [
        'Serpme Kahvaltı' => 'turkish breakfast',
        'Menemen' => 'menemen eggs',
        'Sucuklu Yumurta' => 'sausage eggs',
        'Beyaz Peynir Tabağı' => 'cheese plate',
        'Mercimek Çorbası' => 'lentil soup',
        'Ezogelin Çorbası' => 'tomato soup',
        'Tavuk Suyu Çorba' => 'chicken soup',
        'Humus' => 'hummus',
        'Haydari' => 'yogurt dip',
        'Acılı Ezme' => 'spicy dip',
        'Şakşuka' => 'eggplant appetizer',
        'Çoban Salata' => 'shepherd salad',
        'Mevsim Salata' => 'green salad',
        'Gavurdağı Salata' => 'walnut salad',
        'Etli Güveç' => 'beef stew',
        'Kuru Fasulye & Pilav' => 'beans rice',
        'Tavuk Sote' => 'chicken saute',
        'Tavuk Şiş' => 'chicken skewer',
        'Köfte' => 'meatballs',
        'Izgara Antrikot' => 'grilled steak',
        'Kıymalı Pide' => 'turkish pide minced meat',
        'Kuşbaşılı Pide' => 'turkish pide meat',
        'Kaşarlı Pide' => 'turkish pide cheese',
        'Adana Kebap' => 'adana kebab',
        'Urfa Kebap' => 'urfa kebab',
        'Beyti Sarma' => 'beyti kebab',
        'Künefe' => 'kunefe dessert',
        'Fırın Sütlaç' => 'rice pudding',
        'Baklava' => 'baklava dessert',
        'Ayran' => 'ayran drink',
        'Çay' => 'turkish tea',
        'Türk Kahvesi' => 'turkish coffee',
        'Gazlı İçecek' => 'soft drink',
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

        $content = $this->downloadImage($categoryName, $productName);
        if ($content === null) {
            return;
        }

        $imageSize = @getimagesizefromstring($content);
        $mimeType = is_array($imageSize) ? ($imageSize['mime'] ?? 'image/jpeg') : 'image/jpeg';
        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

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
        if (is_array($imageSize)) {
            $media->setWidth($imageSize[0] ?? null);
            $media->setHeight($imageSize[1] ?? null);
        }
        $media->setAltText($productName);
        $manager->persist($media);

        $productImage = new ProductImage($product, $media, 0);
        $product->addImage($productImage);
        $manager->persist($productImage);
    }

    private function downloadImage(string $categoryName, string $productName): ?string
    {
        $keyword = self::PRODUCT_IMAGE_KEYWORDS[$productName] ?? strtolower($categoryName . ' food');
        $seed = $this->slugify($categoryName . '-' . $productName);

        $urls = [
            sprintf('https://loremflickr.com/1200/800/food,%s', rawurlencode($keyword)),
            sprintf('https://picsum.photos/seed/%s/1200/800', rawurlencode($seed)),
        ];

        $context = stream_context_create([
            'http' => [
                'timeout' => 12,
                'follow_location' => 1,
                'user_agent' => 'qrmenu-fixtures/1.0',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        foreach ($urls as $url) {
            $content = @file_get_contents($url, false, $context);
            if ($content === false || $content === '') {
                continue;
            }

            $size = @getimagesizefromstring($content);
            if ($size === false) {
                continue;
            }

            return $content;
        }

        return null;
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
}
