<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Restaurant;
use App\Enum\SocialPlatform;
use App\Form\RestaurantProfileType;
use App\Infrastructure\Storage\StorageInterface;
use App\Service\SocialLinkService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/restaurant')]
class RestaurantWebController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StorageInterface $storage,
        private readonly SocialLinkService $socialLinkService,
    ) {
    }

    #[Route('', name: 'admin_restaurant_show', methods: ['GET'])]
    public function show(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        return $this->render('admin/restaurant/show.html.twig', [
            'restaurant' => $restaurant,
            'socialLinks' => $this->socialLinkService->listByRestaurant($restaurant),
        ]);
    }

    #[Route('/edit', name: 'admin_restaurant_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        $existingLinks = $this->socialLinkService->listByRestaurant($restaurant);
        $socialLinks = [];
        foreach ($existingLinks as $socialLink) {
            $socialLinks[$socialLink->getPlatform()->value] = $socialLink->getUrl();
        }

        $form = $this->createForm(RestaurantProfileType::class, $restaurant, [
            'social_links' => $socialLinks,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($restaurant->getCountryCode() !== null) {
                $restaurant->setCountryCode(strtoupper($restaurant->getCountryCode()));
            }

            // Handle logo upload
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile instanceof UploadedFile) {
                try {
                    $media = $this->uploadMedia($logoFile, $restaurant);
                    $restaurant->setLogoMedia($media);
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Logo yüklenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.');
                }
            }

            // Handle cover upload
            $coverFile = $form->get('coverFile')->getData();
            if ($coverFile instanceof UploadedFile) {
                try {
                    $media = $this->uploadMedia($coverFile, $restaurant);
                    $restaurant->setCoverMedia($media);
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Kapak fotoğrafı yüklenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.');
                }
            }

            $linksPayload = [];
            $platformFieldMap = [
                SocialPlatform::INSTAGRAM->value => 'instagramUrl',
                SocialPlatform::FACEBOOK->value => 'facebookUrl',
                SocialPlatform::TWITTER->value => 'twitterUrl',
                SocialPlatform::TIKTOK->value => 'tiktokUrl',
                SocialPlatform::WEBSITE->value => 'websiteUrl',
                SocialPlatform::GOOGLE_MAPS->value => 'googleMapsUrl',
            ];

            $sortOrder = 0;
            foreach ($platformFieldMap as $platform => $field) {
                $url = trim((string) $form->get($field)->getData());
                if ($url === '') {
                    continue;
                }

                $linksPayload[] = [
                    'platform' => $platform,
                    'url' => $url,
                    'sortOrder' => $sortOrder,
                ];
                ++$sortOrder;
            }

            $this->socialLinkService->replaceAll($linksPayload, $restaurant);
            $this->em->flush();

            $this->addFlash('success', 'Restoran bilgileri başarıyla güncellendi.');

            return $this->redirectToRoute('admin_restaurant_show');
        }

        return $this->render('admin/restaurant/edit.html.twig', [
            'restaurant' => $restaurant,
            'form' => $form,
        ]);
    }

    private function uploadMedia(UploadedFile $file, Restaurant $restaurant): Media
    {
        $mimeType = $this->resolveMimeType($file);
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \InvalidArgumentException('Sadece JPEG, PNG ve WebP dosyaları yüklenebilir.');
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $mediaUuid = Uuid::uuid7()->toString();
        $storagePath = sprintf('media/%s/%s.%s', $restaurant->getUuid()->toString(), $mediaUuid, $extension);

        // Upload to storage
        $fileContent = file_get_contents($file->getPathname());
        if ($fileContent === false) {
            throw new \RuntimeException('Uploaded file could not be read.');
        }
        $this->storage->put($storagePath, $fileContent, $mimeType);

        $size = @filesize($file->getPathname());
        if (!is_int($size) || $size < 0) {
            throw new \RuntimeException('Uploaded file size could not be determined.');
        }

        // Create media entity
        $media = new Media(
            $file->getClientOriginalName(),
            $storagePath,
            $mimeType,
            $size,
            $restaurant
        );

        $this->em->persist($media);

        return $media;
    }

    private function resolveMimeType(UploadedFile $file): string
    {
        $imageSize = @getimagesize($file->getPathname());
        if (is_array($imageSize) && isset($imageSize['mime']) && is_string($imageSize['mime'])) {
            return $imageSize['mime'];
        }

        return $file->getClientMimeType();
    }
}
