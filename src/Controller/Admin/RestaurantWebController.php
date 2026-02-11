<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Restaurant;
use App\Enum\SocialPlatform;
use App\Form\RestaurantProfileType;
use App\Infrastructure\Storage\StorageInterface;
use App\Service\Image\ImagePipeline;
use App\Service\LanguageContext;
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
        private readonly ImagePipeline $imagePipeline,
        private readonly SocialLinkService $socialLinkService,
        private readonly LanguageContext $languageContext,
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
            'theme_colors' => $this->resolveThemeColors($restaurant),
            'available_locales' => $this->languageContext->getLocaleLabelMap(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($restaurant->getCountryCode() !== null) {
                $restaurant->setCountryCode(strtoupper($restaurant->getCountryCode()));
            }

            $restaurant->setColorOverrides([
                'primary' => $this->sanitizeHexColor((string) $form->get('themePrimaryColor')->getData(), '#9A3412'),
                'secondary' => $this->sanitizeHexColor((string) $form->get('themeSecondaryColor')->getData(), '#0F766E'),
                'background' => $this->sanitizeHexColor((string) $form->get('themeBackgroundColor')->getData(), '#FFF8F1'),
                'surface' => $this->sanitizeHexColor((string) $form->get('themeSurfaceColor')->getData(), '#FFFFFF'),
                'text' => $this->sanitizeHexColor((string) $form->get('themeTextColor')->getData(), '#1F2937'),
                'accent' => $this->sanitizeHexColor((string) $form->get('themeAccentColor')->getData(), '#F59E0B'),
            ]);

            // Handle logo upload
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile instanceof UploadedFile) {
                try {
                    $media = $this->uploadMedia($logoFile, $restaurant, ImagePipeline::PROFILE_LOGO);
                    $restaurant->setLogoMedia($media);
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Logo yüklenirken bir hata oluştu. Lütfen daha sonra tekrar deneyin.');
                }
            }

            // Handle cover upload
            $coverFile = $form->get('coverFile')->getData();
            if ($coverFile instanceof UploadedFile) {
                try {
                    $media = $this->uploadMedia($coverFile, $restaurant, ImagePipeline::PROFILE_COVER);
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

    /**
     * @return array{primary:string,secondary:string,background:string,surface:string,text:string,accent:string}
     */
    private function resolveThemeColors(Restaurant $restaurant): array
    {
        $overrides = $restaurant->getColorOverrides();
        if (!is_array($overrides)) {
            $overrides = [];
        }

        return [
            'primary' => $this->sanitizeHexColor((string) ($overrides['primary'] ?? ''), '#9A3412'),
            'secondary' => $this->sanitizeHexColor((string) ($overrides['secondary'] ?? ''), '#0F766E'),
            'background' => $this->sanitizeHexColor((string) ($overrides['background'] ?? ''), '#FFF8F1'),
            'surface' => $this->sanitizeHexColor((string) ($overrides['surface'] ?? ''), '#FFFFFF'),
            'text' => $this->sanitizeHexColor((string) ($overrides['text'] ?? ''), '#1F2937'),
            'accent' => $this->sanitizeHexColor((string) ($overrides['accent'] ?? ''), '#F59E0B'),
        ];
    }

    private function sanitizeHexColor(string $color, string $fallback): string
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? strtoupper($color) : strtoupper($fallback);
    }

    private function uploadMedia(UploadedFile $file, Restaurant $restaurant, string $profile): Media
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

        $processed = $this->imagePipeline->processUploadedFile($file, $mimeType, $profile);
        $this->storage->put($storagePath, $processed->getContents(), $processed->getMimeType());

        // Create media entity
        $media = new Media(
            $file->getClientOriginalName(),
            $storagePath,
            $processed->getMimeType(),
            $processed->getSize(),
            $restaurant
        );
        $media->setWidth($processed->getWidth());
        $media->setHeight($processed->getHeight());

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
