<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Restaurant;
use App\Entity\QrCode;
use App\Entity\User;
use App\Enum\QrCodeType;
use App\Infrastructure\Pdf\TableLabelGenerator;
use App\Infrastructure\QrCode\QrCodeGenerator;
use App\Infrastructure\Storage\StorageInterface;
use App\Repository\PdfTemplateRepository;
use App\Repository\QrCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/qrcodes')]
class QrCodeWebController extends AbstractController
{
    public function __construct(
        private readonly QrCodeRepository $qrCodeRepository,
        private readonly PdfTemplateRepository $pdfTemplateRepository,
        private readonly QrCodeGenerator $qrCodeGenerator,
        private readonly TableLabelGenerator $tableLabelGenerator,
        private readonly StorageInterface $storage,
        private readonly EntityManagerInterface $em,
        private readonly string $publicMenuUrl,
    ) {
    }

    #[Route('', name: 'admin_qrcode_index', methods: ['GET'])]
    public function index(): Response
    {
        $restaurant = $this->getRestaurantOrThrow();

        $qrCodes = $this->qrCodeRepository->findByRestaurant($restaurant);

        return $this->render('admin/qrcode/index.html.twig', [
            'qrCodes' => $qrCodes,
            'storage' => $this->storage,
        ]);
    }

    #[Route('/create', name: 'admin_qrcode_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();
        $templates = $this->pdfTemplateRepository->findAllActive();
        $batchResults = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('qrcode_create', $request->request->getString('_token'))) {
                $this->addFlash('error', 'Geçersiz CSRF token.');
                return $this->redirectToRoute('admin_qrcode_create');
            }

            try {
                $typeValue = $request->request->getString('type', 'restaurant');
                $type = QrCodeType::from($typeValue);
                $batchMode = $request->request->getString('batch_mode', 'table');
                if ($batchMode === 'table') {
                    $type = QrCodeType::TABLE;
                }
                $batchCount = max(1, min(500, $request->request->getInt('batch_count', 20)));
                $tableStartNumber = max(1, $request->request->getInt('table_start_number', 1));
                $downloadAction = $request->request->getString('action', 'create');

                $tableNumber = null;
                if ($type === QrCodeType::TABLE) {
                    $tableNumber = $request->request->getInt('table_number', $tableStartNumber);
                    if ($tableNumber <= 0) {
                        $this->addFlash('error', 'Masa numarası geçerli olmalıdır.');
                        return $this->render('admin/qrcode/create.html.twig', [
                            'formData' => $request->request->all(),
                            'publicMenuUrl' => $this->publicMenuUrl,
                            'restaurantSlug' => $restaurant->getSlug(),
                            'templates' => $templates,
                            'batchResults' => $batchResults,
                        ]);
                    }
                }

                // Build URL
                $baseUrl = sprintf('%s/%s', rtrim($this->publicMenuUrl, '/'), $restaurant->getSlug());
                $tableBadgeTheme = $this->buildTableBadgeTheme($request);

                // Customization
                $fgColor = $request->request->getString('foreground_color', '#000000') ?: '#000000';
                $bgColor = $request->request->getString('background_color', '#ffffff') ?: '#ffffff';
                $borderRadius = $request->request->getInt('border_radius', 0);
                $qrSize = $this->sanitizeQrSize($request->request->getInt('qr_size', 512));

                // Handle logo upload
                $logoTempPath = null;
                $logoContent = null;
                $logoMimeType = null;
                $logoExt = null;
                $logoFile = $request->files->get('logo');
                if ($logoFile instanceof UploadedFile && $logoFile->isValid()) {
                    $logoTempPath = $logoFile->getPathname();
                    $logoMimeType = $this->resolveMimeType($logoFile);
                    if (!in_array($logoMimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                        throw new \InvalidArgumentException('Logo dosyasi JPEG, PNG veya WebP olmalidir.');
                    }

                    $logoExt = match ($logoMimeType) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        default => 'png',
                    };

                    $logoContent = file_get_contents($logoFile->getPathname());
                    if ($logoContent === false) {
                        throw new \RuntimeException('Logo dosyasi okunamadi.');
                    }
                }

                $labelBackgroundContent = null;
                $labelBackgroundMimeType = null;
                $labelBackgroundExt = null;
                $labelBackgroundFile = $request->files->get('label_background_image');
                if ($labelBackgroundFile instanceof UploadedFile && $labelBackgroundFile->isValid()) {
                    $labelBackgroundMimeType = $this->resolveMimeType($labelBackgroundFile);
                    if (!in_array($labelBackgroundMimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                        throw new \InvalidArgumentException('Arka plan dosyasi JPEG, PNG veya WebP olmalidir.');
                    }

                    $labelBackgroundExt = match ($labelBackgroundMimeType) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        default => 'png',
                    };

                    $labelBackgroundContent = file_get_contents($labelBackgroundFile->getPathname());
                    if ($labelBackgroundContent === false) {
                        throw new \RuntimeException('Arka plan dosyasi okunamadi.');
                    }
                }

                $createdQrCodes = [];
                $shouldCreateTableBatch = $batchMode === 'table' && $type === QrCodeType::TABLE;
                $loops = ($downloadAction === 'create_pdf' && $shouldCreateTableBatch) ? $batchCount : 1;
                for ($i = 0; $i < $loops; $i++) {
                    $currentTableNumber = $type === QrCodeType::TABLE ? ($shouldCreateTableBatch ? ($tableStartNumber + $i) : $tableNumber) : null;
                    $currentLabel = $type === QrCodeType::TABLE
                        ? ($request->request->getString('table_label_prefix') ?: 'Masa') . ' ' . $currentTableNumber
                        : null;
                    $encodedUrl = $currentTableNumber !== null
                        ? sprintf('%s?table=%d', $baseUrl, $currentTableNumber)
                        : $baseUrl;

                    $qrCode = new QrCode($restaurant, $type, $encodedUrl);
                    if ($currentTableNumber !== null) {
                        $qrCode->setTableNumber($currentTableNumber);
                        $qrCode->setTableLabel($currentLabel);
                    } else {
                        $qrCode->setTableLabel($request->request->getString('single_label') ?: 'Ana Menu');
                    }
                    $qrCode->setForegroundColor($fgColor);
                    $qrCode->setBackgroundColor($bgColor);
                    $qrCode->setBorderRadius($borderRadius > 0 ? $borderRadius : null);
                    $qrCode->setQrSize($qrSize);
                    if (is_string($logoContent) && is_string($logoMimeType) && is_string($logoExt)) {
                        $logoStoragePath = sprintf(
                            'qrcodes/%s/logos/%s.%s',
                            $restaurant->getUuid()->toString(),
                            $qrCode->getUuid()->toString(),
                            $logoExt,
                        );
                        $this->storage->put($logoStoragePath, $logoContent, $logoMimeType);
                        $qrCode->setLogoStoragePath($logoStoragePath);
                    }
                    if (is_string($labelBackgroundContent) && is_string($labelBackgroundMimeType) && is_string($labelBackgroundExt)) {
                        $labelBackgroundStoragePath = sprintf(
                            'qrcodes/%s/backgrounds/%s.%s',
                            $restaurant->getUuid()->toString(),
                            $qrCode->getUuid()->toString(),
                            $labelBackgroundExt,
                        );
                        $this->storage->put($labelBackgroundStoragePath, $labelBackgroundContent, $labelBackgroundMimeType);
                        $qrCode->setLabelBackgroundStoragePath($labelBackgroundStoragePath);
                    }
                    $this->em->persist($qrCode);

                    $imageBytes = $this->qrCodeGenerator->generate(
                        $encodedUrl,
                        $qrSize,
                        $fgColor,
                        $bgColor,
                        $borderRadius > 0 ? $borderRadius : null,
                        $logoTempPath,
                    );
                    $storagePath = sprintf('qrcodes/%s/%s.png', $restaurant->getUuid()->toString(), $qrCode->getUuid()->toString());
                    $this->storage->put($storagePath, $imageBytes, 'image/png');
                    $qrCode->setStoragePath($storagePath);

                    $createdQrCodes[] = $qrCode;
                }

                $this->em->flush();

                if ($downloadAction === 'create_pdf') {
                    $templateSlug = $request->request->getString('label_template', 'classic');
                    $selectedTemplate = $this->pdfTemplateRepository->findOneBy(['slug' => $templateSlug, 'isActive' => true]);
                    if ($selectedTemplate === null) {
                        $templateSlug = $templates[0]->getSlug() ?? 'classic';
                    }
                    $templatePrimaryColor = $this->sanitizeHexColor(
                        $request->request->getString('template_primary_color', '#1f2937'),
                        '#1f2937',
                    );
                    $templateBackgroundColor = $this->sanitizeHexColor(
                        $request->request->getString('template_background_color', '#ffffff'),
                        '#ffffff',
                    );

                    foreach ($createdQrCodes as $index => $createdQrCode) {
                        $batchResults[] = [
                            'row' => $index + 1,
                            'tableNumber' => $createdQrCode->getTableNumber(),
                            'previewUrl' => $this->generateUrl('admin_qrcode_preview', [
                                'uuid' => $createdQrCode->getUuid()->toString(),
                            ]),
                            'downloadUrl' => $this->generateUrl('admin_qrcode_download_label_pdf', [
                                'uuid' => $createdQrCode->getUuid()->toString(),
                                'templateSlug' => $templateSlug,
                                'primary' => $templatePrimaryColor,
                                'background' => $templateBackgroundColor,
                                'tableTextColor' => $tableBadgeTheme['textColor'],
                                'tableBgColor' => $tableBadgeTheme['bgColor'],
                                'tableBorderColor' => $tableBadgeTheme['borderColor'],
                                'tableBorderWidth' => $tableBadgeTheme['borderWidth'],
                                'tableBorderRadius' => $tableBadgeTheme['borderRadius'],
                                'tablePosX' => $tableBadgeTheme['posX'],
                                'tablePosY' => $tableBadgeTheme['posY'],
                            ]),
                        ];
                    }

                    if ($batchMode === 'single' && count($batchResults) === 1) {
                        $single = $batchResults[0];
                        $batchResults = [];
                        for ($i = 1; $i <= $batchCount; $i++) {
                            $batchResults[] = [
                                'row' => $i,
                                'tableNumber' => null,
                                'previewUrl' => $single['previewUrl'],
                                'downloadUrl' => $single['downloadUrl'],
                            ];
                        }
                    }

                    $request->getSession()->set('qrcode_batch_results', $batchResults);
                    $this->addFlash('success', sprintf('%d adet PDF linki hazirlandi.', count($batchResults)));

                    return $this->redirectToRoute('admin_qrcode_batch_list');
                }

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
            'templates' => $templates,
            'batchResults' => $batchResults,
        ]);
    }

    #[Route('/batch-list', name: 'admin_qrcode_batch_list', methods: ['GET'])]
    public function batchList(Request $request): Response
    {
        $this->getRestaurantOrThrow();
        $batchResults = $request->getSession()->get('qrcode_batch_results', []);

        return $this->render('admin/qrcode/batch_list.html.twig', [
            'batchResults' => is_array($batchResults) ? $batchResults : [],
        ]);
    }

    #[Route('/{uuid}/download/{format}/{size}', name: 'admin_qrcode_download', methods: ['GET'])]
    public function download(string $uuid, string $format = 'png', int $size = 512): Response
    {
        $restaurant = $this->getRestaurantOrThrow();

        $qrCode = $this->qrCodeRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
        if ($qrCode === null) {
            throw $this->createNotFoundException('QR code not found');
        }

        // Validate size
        $allowedSizes = [128, 256, 384, 512, 768, 1024];
        if (!\in_array($size, $allowedSizes, true)) {
            $size = $this->sanitizeQrSize($qrCode->getQrSize());
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
        $restaurant = $this->getRestaurantOrThrow();

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

    #[Route('/{uuid}/download-label-pdf/{templateSlug}', name: 'admin_qrcode_download_label_pdf', methods: ['GET'])]
    public function downloadLabelPdf(Request $request, string $uuid, string $templateSlug): Response
    {
        $restaurant = $this->getRestaurantOrThrow();

        $qrCode = $this->qrCodeRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
        if ($qrCode === null) {
            throw $this->createNotFoundException('QR code not found');
        }

        $template = $this->pdfTemplateRepository->findOneBy(['slug' => $templateSlug, 'isActive' => true]);
        if ($template === null) {
            throw $this->createNotFoundException('PDF template not found');
        }

        $theme = [
            'primary' => $this->sanitizeHexColor($request->query->getString('primary', '#1f2937'), '#1f2937'),
            'background' => $this->sanitizeHexColor($request->query->getString('background', '#ffffff'), '#ffffff'),
            'tableBadge' => [
                'textColor' => $this->sanitizeHexColor($request->query->getString('tableTextColor', '#111827'), '#111827'),
                'bgColor' => $this->sanitizeHexColor($request->query->getString('tableBgColor', '#FFFFFF'), '#FFFFFF'),
                'borderColor' => $this->sanitizeHexColor($request->query->getString('tableBorderColor', '#1F2937'), '#1F2937'),
                'borderWidth' => max(0, min(8, $request->query->getInt('tableBorderWidth', 1))),
                'borderRadius' => max(0, min(40, $request->query->getInt('tableBorderRadius', 12))),
                'posX' => max(0, min(100, $request->query->getInt('tablePosX', 50))),
                'posY' => max(0, min(100, $request->query->getInt('tablePosY', 73))),
            ],
        ];

        $pdfContent = $this->tableLabelGenerator->generate($template, [$qrCode], $restaurant, $theme);
        $safeTemplate = preg_replace('/[^a-z0-9_-]+/i', '-', $template->getSlug()) ?: 'template';
        $safeTable = $qrCode->getTableNumber() !== null ? 'table-' . $qrCode->getTableNumber() : 'restaurant';
        $filename = sprintf('qr-label-%s-%s.pdf', $safeTemplate, $safeTable);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        ]);
    }

    #[Route('/preview-generate', name: 'admin_qrcode_preview_generate', methods: ['POST'])]
    public function previewGenerate(Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();

        $type = $request->request->getString('type', 'restaurant');
        $tableNumber = $request->request->getInt('table_number', 0);
        $fgColor = $request->request->getString('foreground_color', '#000000');
        $bgColor = $request->request->getString('background_color', '#ffffff');
        $borderRadius = max(0, min(50, $request->request->getInt('border_radius', 0)));
        $qrSize = $this->sanitizeQrSize($request->request->getInt('qr_size', 512));

        $logoTempPath = null;
        $logoFile = $request->files->get('logo');
        if ($logoFile instanceof UploadedFile && $logoFile->isValid()) {
            $logoMimeType = $this->resolveMimeType($logoFile);
            if (in_array($logoMimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $logoTempPath = $logoFile->getPathname();
            }
        }

        $baseUrl = sprintf('%s/%s', rtrim($this->publicMenuUrl, '/'), $restaurant->getSlug());
        $encodedUrl = ($type === 'table' && $tableNumber > 0)
            ? sprintf('%s?table=%d', $baseUrl, $tableNumber)
            : $baseUrl;

        $content = $this->qrCodeGenerator->generate(
            $encodedUrl,
            $qrSize,
            $fgColor,
            $bgColor,
            $borderRadius > 0 ? $borderRadius : null,
            $logoTempPath,
        );

        return new Response($content, 200, [
            'Content-Type' => 'image/png',
        ]);
    }

    #[Route('/{uuid}/delete', name: 'admin_qrcode_delete', methods: ['POST'])]
    public function delete(string $uuid, Request $request): Response
    {
        $restaurant = $this->getRestaurantOrThrow();

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
            if ($qrCode->getLabelBackgroundStoragePath() !== null) {
                $this->storage->delete($qrCode->getLabelBackgroundStoragePath());
            }

            $this->em->remove($qrCode);
            $this->em->flush();

            $this->addFlash('success', 'QR kod başarıyla silindi.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Bir hata oluştu: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_qrcode_index');
    }

    private function getRestaurantOrThrow(): Restaurant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $restaurant = $user->getRestaurant();
        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        return $restaurant;
    }

    private function resolveMimeType(UploadedFile $file): string
    {
        $imageSize = @getimagesize($file->getPathname());
        if (is_array($imageSize) && isset($imageSize['mime']) && is_string($imageSize['mime'])) {
            return $imageSize['mime'];
        }

        return $file->getClientMimeType();
    }

    private function sanitizeHexColor(string $color, string $fallback): string
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? strtoupper($color) : strtoupper($fallback);
    }

    private function sanitizeQrSize(int $size): int
    {
        $allowedSizes = [128, 256, 384, 512, 768, 1024];

        return in_array($size, $allowedSizes, true) ? $size : 512;
    }

    /**
     * @return array{textColor:string,bgColor:string,borderColor:string,borderWidth:int,borderRadius:int,posX:int,posY:int}
     */
    private function buildTableBadgeTheme(Request $request): array
    {
        return [
            'textColor' => $this->sanitizeHexColor($request->request->getString('table_text_color', '#111827'), '#111827'),
            'bgColor' => $this->sanitizeHexColor($request->request->getString('table_bg_color', '#ffffff'), '#ffffff'),
            'borderColor' => $this->sanitizeHexColor($request->request->getString('table_border_color', '#1f2937'), '#1f2937'),
            'borderWidth' => max(0, min(8, $request->request->getInt('table_border_width', 1))),
            'borderRadius' => max(0, min(40, $request->request->getInt('table_border_radius', 12))),
            'posX' => max(0, min(100, $request->request->getInt('table_pos_x', 50))),
            'posY' => max(0, min(100, $request->request->getInt('table_pos_y', 73))),
        ];
    }
}
