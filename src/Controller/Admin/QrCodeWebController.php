<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\QrCode;
use App\Enum\QrCodeType;
use App\Infrastructure\QrCode\QrCodeGenerator;
use App\Infrastructure\Storage\StorageInterface;
use App\Repository\QrCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/qrcodes')]
class QrCodeWebController extends AbstractController
{
    public function __construct(
        private readonly QrCodeRepository $qrCodeRepository,
        private readonly QrCodeGenerator $qrCodeGenerator,
        private readonly StorageInterface $storage,
        private readonly EntityManagerInterface $em,
        private readonly string $publicMenuUrl,
    ) {
    }

    #[Route('', name: 'admin_qrcode_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        $qrCodes = $this->qrCodeRepository->findByRestaurant($restaurant);

        return $this->render('admin/qrcode/index.html.twig', [
            'qrCodes' => $qrCodes,
            'storage' => $this->storage,
        ]);
    }

    #[Route('/create', name: 'admin_qrcode_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('qrcode_create', $request->request->getString('_token'))) {
                $this->addFlash('error', 'Geçersiz CSRF token.');
                return $this->redirectToRoute('admin_qrcode_create');
            }

            try {
                $typeValue = $request->request->getString('type', 'restaurant');
                $type = QrCodeType::from($typeValue);

                $tableNumber = null;
                $tableLabel = null;
                if ($type === QrCodeType::TABLE) {
                    $tableNumber = $request->request->getInt('table_number');
                    $tableLabel = $request->request->getString('table_label') ?: sprintf('Masa %d', $tableNumber);
                    if ($tableNumber <= 0) {
                        $this->addFlash('error', 'Masa numarası geçerli olmalıdır.');
                        return $this->render('admin/qrcode/create.html.twig', [
                            'formData' => $request->request->all(),
                            'publicMenuUrl' => $this->publicMenuUrl,
                            'restaurantSlug' => $restaurant->getSlug(),
                        ]);
                    }
                }

                // Build URL
                $baseUrl = sprintf('%s/%s', rtrim($this->publicMenuUrl, '/'), $restaurant->getSlug());
                $encodedUrl = $type === QrCodeType::TABLE && $tableNumber !== null
                    ? sprintf('%s?table=%d', $baseUrl, $tableNumber)
                    : $baseUrl;

                // Customization
                $fgColor = $request->request->getString('foreground_color', '#000000') ?: '#000000';
                $bgColor = $request->request->getString('background_color', '#ffffff') ?: '#ffffff';
                $borderRadius = $request->request->getInt('border_radius', 0);

                // Create entity
                $qrCode = new QrCode($restaurant, $type, $encodedUrl);
                if ($type === QrCodeType::TABLE) {
                    $qrCode->setTableNumber($tableNumber);
                    $qrCode->setTableLabel($tableLabel);
                }
                $qrCode->setForegroundColor($fgColor);
                $qrCode->setBackgroundColor($bgColor);
                $qrCode->setBorderRadius($borderRadius > 0 ? $borderRadius : null);

                $this->em->persist($qrCode);
                $this->em->flush();

                // Handle logo upload
                $logoTempPath = null;
                $logoFile = $request->files->get('logo');
                if ($logoFile !== null && $logoFile->isValid()) {
                    $logoTempPath = $logoFile->getPathname();
                    // Store logo
                    $logoExt = $logoFile->guessExtension() ?? 'png';
                    $logoStoragePath = sprintf('qrcodes/%s/logos/%s.%s', $restaurant->getUuid()->toString(), $qrCode->getUuid()->toString(), $logoExt);
                    $this->storage->put($logoStoragePath, $logoFile->getContent(), $logoFile->getMimeType());
                    $qrCode->setLogoStoragePath($logoStoragePath);
                }

                // Generate QR code PNG
                $imageBytes = $this->qrCodeGenerator->generate(
                    $encodedUrl,
                    512,
                    $fgColor,
                    $bgColor,
                    $borderRadius > 0 ? $borderRadius : null,
                    $logoTempPath,
                );
                $storagePath = sprintf('qrcodes/%s/%s.png', $restaurant->getUuid()->toString(), $qrCode->getUuid()->toString());
                $this->storage->put($storagePath, $imageBytes, 'image/png');
                $qrCode->setStoragePath($storagePath);

                $this->em->flush();

                $this->addFlash('success', 'QR kod başarıyla oluşturuldu.');
                return $this->redirectToRoute('admin_qrcode_index');
            } catch (\ValueError $e) {
                $this->addFlash('error', 'Geçersiz QR kod tipi.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Bir hata oluştu: ' . $e->getMessage());
            }
        }

        return $this->render('admin/qrcode/create.html.twig', [
            'formData' => $request->isMethod('POST') ? $request->request->all() : [],
            'publicMenuUrl' => $this->publicMenuUrl,
            'restaurantSlug' => $restaurant->getSlug(),
        ]);
    }

    #[Route('/{uuid}/download/{format}/{size}', name: 'admin_qrcode_download', methods: ['GET'])]
    public function download(string $uuid, string $format = 'png', int $size = 512): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        $qrCode = $this->qrCodeRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
        if ($qrCode === null) {
            throw $this->createNotFoundException('QR code not found');
        }

        // Validate size
        $allowedSizes = [256, 512, 1024];
        if (!\in_array($size, $allowedSizes, true)) {
            $size = 512;
        }

        // Download logo to temp if exists
        $logoTempPath = null;
        if ($qrCode->getLogoStoragePath() !== null) {
            $logoContent = $this->storage->get($qrCode->getLogoStoragePath());
            if ($logoContent !== null) {
                $logoTempPath = tempnam(sys_get_temp_dir(), 'qr_logo_');
                file_put_contents($logoTempPath, $logoContent);
            }
        }

        try {
            if ($format === 'svg') {
                $content = $this->qrCodeGenerator->generateSvg(
                    $qrCode->getEncodedUrl(),
                    $size,
                    $qrCode->getForegroundColor(),
                    $qrCode->getBackgroundColor(),
                    $qrCode->getBorderRadius(),
                );
                $mimeType = 'image/svg+xml';
                $extension = 'svg';
            } else {
                $content = $this->qrCodeGenerator->generate(
                    $qrCode->getEncodedUrl(),
                    $size,
                    $qrCode->getForegroundColor(),
                    $qrCode->getBackgroundColor(),
                    $qrCode->getBorderRadius(),
                    $logoTempPath,
                );
                $mimeType = 'image/png';
                $extension = 'png';
            }
        } finally {
            if ($logoTempPath !== null && file_exists($logoTempPath)) {
                unlink($logoTempPath);
            }
        }

        $filename = sprintf('qr-%s-%dpx.%s', $qrCode->getUuid()->toString(), $size, $extension);

        return new Response($content, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        ]);
    }

    #[Route('/{uuid}/preview', name: 'admin_qrcode_preview', methods: ['GET'])]
    public function preview(string $uuid): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        $qrCode = $this->qrCodeRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
        if ($qrCode === null) {
            throw $this->createNotFoundException('QR code not found');
        }

        if ($qrCode->getStoragePath() === null) {
            throw $this->createNotFoundException('QR code image not found');
        }

        $content = $this->storage->get($qrCode->getStoragePath());

        return new Response($content, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    #[Route('/preview-generate', name: 'admin_qrcode_preview_generate', methods: ['POST'])]
    public function previewGenerate(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            return new Response('', 404);
        }

        $type = $request->request->getString('type', 'restaurant');
        $tableNumber = $request->request->getInt('table_number', 0);
        $fgColor = $request->request->getString('foreground_color', '#000000');
        $bgColor = $request->request->getString('background_color', '#ffffff');

        $baseUrl = sprintf('%s/%s', rtrim($this->publicMenuUrl, '/'), $restaurant->getSlug());
        $encodedUrl = ($type === 'table' && $tableNumber > 0)
            ? sprintf('%s?table=%d', $baseUrl, $tableNumber)
            : $baseUrl;

        $content = $this->qrCodeGenerator->generate($encodedUrl, 256, $fgColor, $bgColor);

        return new Response($content, 200, [
            'Content-Type' => 'image/png',
        ]);
    }

    #[Route('/{uuid}/delete', name: 'admin_qrcode_delete', methods: ['POST'])]
    public function delete(string $uuid, Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        if (!$this->isCsrfTokenValid('qrcode_delete_' . $uuid, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Geçersiz CSRF token.');
            return $this->redirectToRoute('admin_qrcode_index');
        }

        try {
            $qrCode = $this->qrCodeRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
            if ($qrCode === null) {
                $this->addFlash('error', 'QR kod bulunamadı.');
                return $this->redirectToRoute('admin_qrcode_index');
            }

            // Delete images from storage
            if ($qrCode->getStoragePath() !== null) {
                $this->storage->delete($qrCode->getStoragePath());
            }
            if ($qrCode->getLogoStoragePath() !== null) {
                $this->storage->delete($qrCode->getLogoStoragePath());
            }

            $this->em->remove($qrCode);
            $this->em->flush();

            $this->addFlash('success', 'QR kod başarıyla silindi.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Bir hata oluştu: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_qrcode_index');
    }
}
