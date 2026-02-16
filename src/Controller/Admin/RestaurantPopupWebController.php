<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Restaurant;
use App\Entity\RestaurantPopup;
use App\Entity\User;
use App\Infrastructure\Storage\StorageInterface;
use App\Repository\RestaurantPopupRepository;
use App\Service\Image\ImagePipeline;
use App\Service\LanguageContext;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/popups')]
class RestaurantPopupWebController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RestaurantPopupRepository $popupRepository,
        private readonly StorageInterface $storage,
        private readonly ImagePipeline $imagePipeline,
        private readonly LanguageContext $languageContext,
    ) {
    }

    #[Route('', name: 'admin_popup_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $locale = $this->languageContext->resolveAdminLocale($request, $restaurant->getDefaultLocale(), $restaurant->getEnabledLocales());

        return $this->render('admin/popup/index.html.twig', [
            'popups' => $this->popupRepository->findByRestaurantOrdered($restaurant),
            'currentLocale' => $locale,
            'nowAt' => new \DateTimeImmutable(),
        ]);
    }

    #[Route('/create', name: 'admin_popup_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $locale = $this->languageContext->resolveAdminLocale($request, $restaurant->getDefaultLocale(), $restaurant->getEnabledLocales());

        if (!$this->isCsrfTokenValid('popup_create', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Geçersiz CSRF token.');
            return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
        }

        $title = trim($request->request->getString('title'));
        $startsAt = $this->parseDateTimeInput($request->request->getString('starts_at'));
        $endsAt = $this->parseDateTimeInput($request->request->getString('ends_at'));

        if ($title === '' || !$startsAt instanceof \DateTimeImmutable || !$endsAt instanceof \DateTimeImmutable) {
            $this->addFlash('error', 'Başlık, başlangıç ve bitiş tarihi zorunludur.');
            return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
        }

        if ($endsAt < $startsAt) {
            $this->addFlash('error', 'Bitiş tarihi başlangıç tarihinden önce olamaz.');
            return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
        }

        $popup = new RestaurantPopup($restaurant, $title, $startsAt, $endsAt);
        $popup->setIsActive($request->request->getBoolean('is_active', true));
        $popup->setContentHtml($this->sanitizePopupHtml($request->request->getString('content_html')));
        [$buttonLabel, $buttonUrl, $buttonError] = $this->resolvePopupButton(
            $request->request->getString('button_label'),
            $request->request->getString('button_url')
        );
        if ($buttonError !== null) {
            $this->addFlash('error', $buttonError);
            return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
        }
        $popup->setButtonLabel($buttonLabel);
        $popup->setButtonUrl($buttonUrl);

        $imageFile = $request->files->get('image_file');
        if ($imageFile instanceof UploadedFile) {
            try {
                $popup->setImageMedia($this->uploadMedia($imageFile, $restaurant));
            } catch (\Throwable) {
                $this->addFlash('error', 'Popup görseli yüklenirken bir hata oluştu.');
                return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
            }
        }

        if ($popup->getImageMedia() === null && $popup->getContentHtml() === null) {
            $this->addFlash('error', 'Popup için görsel veya HTML içerik girmelisiniz.');
            return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
        }

        $this->em->persist($popup);
        $this->em->flush();

        $this->addFlash('success', 'Popup başarıyla oluşturuldu.');

        return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
    }

    #[Route('/{uuid}/update', name: 'admin_popup_update', methods: ['POST'])]
    public function update(string $uuid, Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $locale = $this->languageContext->resolveAdminLocale($request, $restaurant->getDefaultLocale(), $restaurant->getEnabledLocales());

        if (!$this->isCsrfTokenValid('popup_update_' . $uuid, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Geçersiz CSRF token.');
            return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
        }

        $popup = $this->popupRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
        if (!$popup instanceof RestaurantPopup) {
            $this->addFlash('error', 'Popup bulunamadı.');
            return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
        }

        $title = trim($request->request->getString('title'));
        $startsAt = $this->parseDateTimeInput($request->request->getString('starts_at'));
        $endsAt = $this->parseDateTimeInput($request->request->getString('ends_at'));

        if ($title === '' || !$startsAt instanceof \DateTimeImmutable || !$endsAt instanceof \DateTimeImmutable) {
            $this->addFlash('error', 'Başlık, başlangıç ve bitiş tarihi zorunludur.');
            return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
        }

        if ($endsAt < $startsAt) {
            $this->addFlash('error', 'Bitiş tarihi başlangıç tarihinden önce olamaz.');
            return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
        }

        $popup->setTitle($title);
        $popup->setStartsAt($startsAt);
        $popup->setEndsAt($endsAt);
        $popup->setIsActive($request->request->getBoolean('is_active', false));
        $popup->setContentHtml($this->sanitizePopupHtml($request->request->getString('content_html')));
        [$buttonLabel, $buttonUrl, $buttonError] = $this->resolvePopupButton(
            $request->request->getString('button_label'),
            $request->request->getString('button_url')
        );
        if ($buttonError !== null) {
            $this->addFlash('error', $buttonError);
            return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
        }
        $popup->setButtonLabel($buttonLabel);
        $popup->setButtonUrl($buttonUrl);

        if ($request->request->getBoolean('remove_image', false)) {
            $popup->setImageMedia(null);
        }

        $imageFile = $request->files->get('image_file');
        if ($imageFile instanceof UploadedFile) {
            try {
                $popup->setImageMedia($this->uploadMedia($imageFile, $restaurant));
            } catch (\Throwable) {
                $this->addFlash('error', 'Popup görseli yüklenirken bir hata oluştu.');
                return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
            }
        }

        if ($popup->getImageMedia() === null && $popup->getContentHtml() === null) {
            $this->addFlash('error', 'Popup için görsel veya HTML içerik girmelisiniz.');
            return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
        }

        $this->em->flush();

        $this->addFlash('success', 'Popup güncellendi.');

        return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
    }

    #[Route('/{uuid}/delete', name: 'admin_popup_delete', methods: ['POST'])]
    public function delete(string $uuid, Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $locale = $this->languageContext->resolveAdminLocale($request, $restaurant->getDefaultLocale(), $restaurant->getEnabledLocales());

        if (!$this->isCsrfTokenValid('popup_delete_' . $uuid, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Geçersiz CSRF token.');
            return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
        }

        $popup = $this->popupRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
        if (!$popup instanceof RestaurantPopup) {
            $this->addFlash('error', 'Popup bulunamadı.');
            return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
        }

        $this->em->remove($popup);
        $this->em->flush();

        $this->addFlash('success', 'Popup silindi.');

        return $this->redirectToRoute('admin_popup_index', ['lang' => $locale]);
    }

    private function getRestaurantOrThrow(): Restaurant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $restaurant = $user->getRestaurant();
        if (!$restaurant instanceof Restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        return $restaurant;
    }

    private function parseDateTimeInput(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $value);
        if (!$dateTime instanceof \DateTimeImmutable) {
            return null;
        }

        $errors = \DateTimeImmutable::getLastErrors();
        if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            return null;
        }

        return $dateTime;
    }

    private function sanitizePopupHtml(string $rawHtml): ?string
    {
        $rawHtml = trim($rawHtml);
        if ($rawHtml === '') {
            return null;
        }

        $withoutScripts = preg_replace('/<script\\b[^>]*>.*?<\\/script>/is', '', $rawHtml) ?? '';
        $filtered = strip_tags(
            $withoutScripts,
            '<p><br><strong><b><em><i><u><ul><ol><li><a><img><h1><h2><h3><h4><h5><h6><blockquote><span><div>'
        );
        $filtered = preg_replace('/on[a-z]+\\s*=\\s*"[^"]*"/i', '', $filtered) ?? '';
        $filtered = preg_replace("/on[a-z]+\\s*=\\s*'[^']*'/i", '', $filtered) ?? '';
        $filtered = preg_replace('/javascript:/i', '', $filtered) ?? '';
        $filtered = trim($filtered);

        return $filtered !== '' ? $filtered : null;
    }

    /**
     * @return array{0:?string,1:?string,2:?string}
     */
    private function resolvePopupButton(string $rawLabel, string $rawUrl): array
    {
        $label = trim($rawLabel);
        $url = trim($rawUrl);

        $label = $label !== '' ? $label : null;
        $url = $url !== '' ? $url : null;

        if ($label === null && $url === null) {
            return [null, null, null];
        }

        if ($label === null || $url === null) {
            return [null, null, 'Buton metni ve link birlikte girilmelidir.'];
        }

        if (strlen($label) > 80) {
            return [null, null, 'Buton metni en fazla 80 karakter olabilir.'];
        }

        $lowerUrl = strtolower($url);
        if (str_starts_with($lowerUrl, 'javascript:')) {
            return [null, null, 'Gecersiz link formati.'];
        }

        if (preg_match('/^https?:\/\//i', $url) === 1) {
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                return [null, null, 'Gecersiz buton linki.'];
            }

            return [$label, $url, null];
        }

        if (preg_match('/^(\/|\?|#)/', $url) === 1) {
            return [$label, $url, null];
        }

        return [null, null, 'Link http:// veya https:// ile baslamali ya da /, ?, # ile relatif olmalidir.'];
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

        $processed = $this->imagePipeline->processUploadedFile($file, $mimeType, ImagePipeline::PROFILE_CATALOG);
        $this->storage->put($storagePath, $processed->getContents(), $processed->getMimeType());

        $media = new Media(
            $file->getClientOriginalName(),
            $storagePath,
            $processed->getMimeType(),
            $processed->getSize(),
            $restaurant,
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
