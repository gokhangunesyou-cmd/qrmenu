<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use App\Entity\Media;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\Restaurant;
use App\Enum\ProductStatus;
use App\Infrastructure\Storage\StorageInterface;
use App\Service\Image\ImagePipeline;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'app:catalog:import-json',
    description: 'Import restaurant categories/products/images from a JSON file using configured storage (S3/local).',
)]
final class ImportRestaurantCatalogFromJsonCommand extends Command
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StorageInterface $storage,
        private readonly ImagePipeline $imagePipeline,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('restaurant-slug', InputArgument::REQUIRED, 'Restaurant slug.')
            ->addArgument('json-path', InputArgument::REQUIRED, 'Path to JSON file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $restaurantSlug = trim((string) $input->getArgument('restaurant-slug'));
        $jsonPath = trim((string) $input->getArgument('json-path'));

        if ($restaurantSlug === '') {
            $output->writeln('<error>Restaurant slug is required.</error>');

            return Command::INVALID;
        }

        if ($jsonPath === '' || !is_file($jsonPath)) {
            $output->writeln(sprintf('<error>JSON file not found: %s</error>', $jsonPath));

            return Command::INVALID;
        }

        $restaurant = $this->em->getRepository(Restaurant::class)->findOneBy(['slug' => $restaurantSlug]);
        if (!$restaurant instanceof Restaurant) {
            $output->writeln(sprintf('<error>Restaurant not found for slug: %s</error>', $restaurantSlug));

            return Command::FAILURE;
        }

        $rawJson = file_get_contents($jsonPath);
        if (!is_string($rawJson) || $rawJson === '') {
            $output->writeln('<error>JSON file could not be read.</error>');

            return Command::FAILURE;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $output->writeln(sprintf('<error>Invalid JSON: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        if (!is_array($decoded)) {
            $output->writeln('<error>JSON root must be an array.</error>');

            return Command::FAILURE;
        }

        $restaurant->setMenuTemplate('boutique');
        $restaurant->setCurrencyCode('TRY');
        $restaurant->setEnabledLocales(['tr']);
        $restaurant->setIsActive(true);

        $httpClient = HttpClient::create([
            'timeout' => 45,
            'max_redirects' => 5,
        ]);

        $categoryOrderMap = [];
        $productOrderMap = [];
        $processedProducts = 0;
        $uploadedImages = 0;
        $warnings = 0;

        foreach ($decoded as $index => $row) {
            if (!is_array($row)) {
                ++$warnings;
                $output->writeln(sprintf('<comment>Skipping row %d: invalid row shape.</comment>', $index));

                continue;
            }

            $productName = trim((string) ($row['urun_adi'] ?? ''));
            $categoryName = trim((string) ($row['kategori'] ?? ''));
            $priceRaw = trim((string) ($row['fiyat'] ?? ''));
            $description = trim((string) ($row['aciklama'] ?? ''));
            $imageUrl = trim((string) ($row['resim'] ?? ''));

            if ($productName === '' || $categoryName === '') {
                ++$warnings;
                $output->writeln(sprintf('<comment>Skipping row %d: missing product/category name.</comment>', $index));

                continue;
            }

            if (!isset($categoryOrderMap[$categoryName])) {
                $categoryOrderMap[$categoryName] = count($categoryOrderMap) + 1;
                $productOrderMap[$categoryName] = 0;
            }
            $productOrderMap[$categoryName] += 1;

            $category = $this->em->getRepository(Category::class)->findOneBy([
                'restaurant' => $restaurant,
                'name' => $categoryName,
            ]);
            if (!$category instanceof Category) {
                $category = new Category($categoryName, $restaurant);
                $this->em->persist($category);
            }
            $category->setSortOrder((int) $categoryOrderMap[$categoryName]);
            $category->setIsActive(true);

            $product = $this->em->getRepository(Product::class)->findOneBy([
                'restaurant' => $restaurant,
                'name' => $productName,
            ]);
            if (!$product instanceof Product) {
                $product = new Product($productName, $this->normalizePrice($priceRaw), $category, $restaurant);
                $this->em->persist($product);
            }

            $product->setCategory($category);
            $product->setName($productName);
            $product->setPrice($this->normalizePrice($priceRaw));
            $product->setDescription($description !== '' ? $description : null);
            $product->setSortOrder((int) $productOrderMap[$categoryName]);
            $product->setIsActive(true);
            $product->setStatus(ProductStatus::APPROVED);
            $product->setSubmittedAt(new \DateTimeImmutable());

            if ($imageUrl !== '') {
                try {
                    $media = $this->downloadAndStoreImage($httpClient, $imageUrl, $productName, $restaurant);
                    $this->replaceProductImages($product, $media);
                    ++$uploadedImages;
                } catch (\Throwable $e) {
                    ++$warnings;
                    $output->writeln(sprintf('<comment>Image skipped for "%s": %s</comment>', $productName, $e->getMessage()));
                }
            }

            ++$processedProducts;
        }

        $this->em->flush();

        $output->writeln(sprintf(
            '<info>Import completed. products=%d images=%d warnings=%d</info>',
            $processedProducts,
            $uploadedImages,
            $warnings
        ));

        return Command::SUCCESS;
    }

    private function replaceProductImages(Product $product, Media $media): void
    {
        $existingImages = $product->getImages()->toArray();
        foreach ($existingImages as $existingImage) {
            if (!$existingImage instanceof ProductImage) {
                continue;
            }

            $product->removeImage($existingImage);
            $this->em->remove($existingImage);
        }

        $newImage = new ProductImage($product, $media, 0);
        $product->addImage($newImage);
        $this->em->persist($newImage);
    }

    private function downloadAndStoreImage(
        \Symfony\Contracts\HttpClient\HttpClientInterface $httpClient,
        string $imageUrl,
        string $productName,
        Restaurant $restaurant,
    ): Media {
        $response = $httpClient->request('GET', $imageUrl);
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf('HTTP status %d', $statusCode));
        }

        $binary = $response->getContent();
        if ($binary === '') {
            throw new \RuntimeException('empty response body');
        }

        $mimeType = $this->resolveMimeType($binary);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \RuntimeException(sprintf('unsupported mime type: %s', $mimeType));
        }

        $processedOriginal = $this->imagePipeline->processBinary($binary, $mimeType, ImagePipeline::PROFILE_CATALOG);
        $extension = $this->extensionFromMime($processedOriginal->getMimeType());

        $mediaUuid = Uuid::uuid7()->toString();
        $restaurantUuid = $restaurant->getUuid()->toString();
        $storagePath = sprintf('media/%s/%s.%s', $restaurantUuid, $mediaUuid, $extension);
        $thumbPath = sprintf('media/%s/thumbs/%s.%s', $restaurantUuid, $mediaUuid, $extension);

        $this->storage->put($storagePath, $processedOriginal->getContents(), $processedOriginal->getMimeType());

        $thumb = $this->imagePipeline->processBinary(
            $processedOriginal->getContents(),
            $processedOriginal->getMimeType(),
            ImagePipeline::PROFILE_THUMB
        );
        $this->storage->put($thumbPath, $thumb->getContents(), $thumb->getMimeType());

        $media = new Media(
            $this->buildFilename($productName, $extension),
            $storagePath,
            $processedOriginal->getMimeType(),
            $processedOriginal->getSize(),
            $restaurant
        );
        $media->setWidth($processedOriginal->getWidth());
        $media->setHeight($processedOriginal->getHeight());
        $this->em->persist($media);

        return $media;
    }

    private function resolveMimeType(string $binary): string
    {
        $imageInfo = @getimagesizefromstring($binary);
        if (is_array($imageInfo) && isset($imageInfo['mime']) && is_string($imageInfo['mime'])) {
            return $imageInfo['mime'];
        }

        return 'application/octet-stream';
    }

    private function extensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function buildFilename(string $productName, string $extension): string
    {
        $safe = preg_replace('/[^a-z0-9]+/i', '-', strtolower($productName)) ?? 'product';
        $safe = trim($safe, '-');
        if ($safe === '') {
            $safe = 'product';
        }

        return sprintf('%s.%s', $safe, $extension);
    }

    private function normalizePrice(string $raw): string
    {
        $price = strtoupper(trim($raw));
        $price = str_replace(['TL', 'TRY', '₺', ' '], '', $price);

        if ((bool) preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $price)) {
            $price = str_replace('.', '', $price);
            $price = str_replace(',', '.', $price);
        } else {
            $price = str_replace(',', '.', $price);
        }

        $price = preg_replace('/[^0-9.]/', '', $price) ?? '';
        if ($price === '') {
            return '0.00';
        }

        return number_format((float) $price, 2, '.', '');
    }
}
