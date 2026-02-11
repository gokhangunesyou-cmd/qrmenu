<?php

namespace App\Infrastructure\Pdf;

use App\Entity\PdfTemplate;
use App\Entity\QrCode;
use App\Entity\Restaurant;
use App\Infrastructure\QrCode\QrCodeGenerator;
use App\Infrastructure\Storage\StorageInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class TableLabelGenerator
{
    public function __construct(
        private readonly Environment $twig,
        private readonly QrCodeGenerator $qrCodeGenerator,
        private readonly StorageInterface $storage,
    ) {
    }

    /**
     * Generate a PDF containing QR code labels for the given QR codes.
     *
     * @param QrCode[] $qrCodes
     * @return string Binary PDF content
     */
    public function generate(PdfTemplate $template, array $qrCodes, Restaurant $restaurant, array $theme = []): string
    {
        $qrImageDataMap = [];
        $labelBackgroundDataMap = [];
        foreach ($qrCodes as $qrCode) {
            $qrImageDataMap[$qrCode->getUuid()->toString()] = $this->buildQrDataUri($qrCode);
            $labelBackgroundDataMap[$qrCode->getUuid()->toString()] = $this->buildLabelBackgroundDataUri($qrCode);
        }

        $restaurantLogoDataUri = $this->buildRestaurantLogoDataUri($restaurant);

        $html = $this->twig->render($template->getTemplatePath(), [
            'restaurant' => $restaurant,
            'qrCodes' => $qrCodes,
            'template' => $template,
            'theme' => $this->buildTheme($theme),
            'qrImageDataMap' => $qrImageDataMap,
            'labelBackgroundDataMap' => $labelBackgroundDataMap,
            'restaurantLogoDataUri' => $restaurantLogoDataUri,
        ]);

        $options = new Options();
        $options->setIsRemoteEnabled(true);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($template->getPageSize(), 'portrait');
        $dompdf->render();

        return $dompdf->output() ?: '';
    }

    private function buildQrDataUri(QrCode $qrCode): string
    {
        $logoTempPath = null;
        if ($qrCode->getLogoStoragePath() !== null && $this->storage->exists($qrCode->getLogoStoragePath())) {
            $logoContent = $this->storage->get($qrCode->getLogoStoragePath());
            $logoTempPath = tempnam(sys_get_temp_dir(), 'qr_pdf_logo_');
            if ($logoTempPath !== false) {
                file_put_contents($logoTempPath, $logoContent);
            } else {
                $logoTempPath = null;
            }
        }

        try {
            $qrBytes = $this->qrCodeGenerator->generate(
                $qrCode->getEncodedUrl(),
                720,
                $qrCode->getForegroundColor(),
                $qrCode->getBackgroundColor(),
                $qrCode->getBorderRadius(),
                $logoTempPath,
            );
        } finally {
            if (\is_string($logoTempPath) && file_exists($logoTempPath)) {
                unlink($logoTempPath);
            }
        }

        return 'data:image/png;base64,' . base64_encode($qrBytes);
    }

    private function buildRestaurantLogoDataUri(Restaurant $restaurant): ?string
    {
        $logoMedia = $restaurant->getLogoMedia();
        if ($logoMedia === null) {
            return null;
        }

        $storagePath = $logoMedia->getStoragePath();
        if (!$this->storage->exists($storagePath)) {
            return null;
        }

        $mimeType = $logoMedia->getMimeType() !== '' ? $logoMedia->getMimeType() : 'image/png';

        return sprintf(
            'data:%s;base64,%s',
            $mimeType,
            base64_encode($this->storage->get($storagePath)),
        );
    }

    private function buildLabelBackgroundDataUri(QrCode $qrCode): ?string
    {
        $path = $qrCode->getLabelBackgroundStoragePath();
        if ($path === null || !$this->storage->exists($path)) {
            return null;
        }

        $content = $this->storage->get($path);
        $mimeType = $this->guessImageMimeType($content);

        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($content));
    }

    private function buildTheme(array $theme): array
    {
        $primary = $this->sanitizeHexColor($theme['primary'] ?? '#1f2937', '#1f2937');
        $background = $this->sanitizeHexColor($theme['background'] ?? '#ffffff', '#ffffff');
        $tableBadge = is_array($theme['tableBadge'] ?? null) ? $theme['tableBadge'] : [];
        $tableTextColor = $this->sanitizeHexColor((string) ($tableBadge['textColor'] ?? '#111827'), '#111827');
        $tableBgColor = $this->sanitizeHexColor((string) ($tableBadge['bgColor'] ?? '#ffffff'), '#ffffff');
        $tableBorderColor = $this->sanitizeHexColor((string) ($tableBadge['borderColor'] ?? '#1f2937'), '#1f2937');
        $tableBorderWidth = max(0, min(8, (int) ($tableBadge['borderWidth'] ?? 1)));
        $tableBorderRadius = max(0, min(40, (int) ($tableBadge['borderRadius'] ?? 12)));
        $tablePosX = max(0, min(100, (int) ($tableBadge['posX'] ?? 50)));
        $tablePosY = max(0, min(100, (int) ($tableBadge['posY'] ?? 73)));

        return [
            'primary' => $primary,
            'primaryDark' => $this->darkenHex($primary, 0.20),
            'primaryLight' => $this->lightenHex($primary, 0.35),
            'background' => $background,
            'surface' => $this->lightenHex($background, 0.03),
            'text' => $this->darkenHex($primary, 0.45),
            'muted' => $this->darkenHex($background, 0.45),
            'divider' => $this->lightenHex($primary, 0.50),
            'tableBadge' => [
                'textColor' => $tableTextColor,
                'bgColor' => $tableBgColor,
                'borderColor' => $tableBorderColor,
                'borderWidth' => $tableBorderWidth,
                'borderRadius' => $tableBorderRadius,
                'posX' => $tablePosX,
                'posY' => $tablePosY,
            ],
        ];
    }

    private function sanitizeHexColor(string $color, string $fallback): string
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? strtoupper($color) : strtoupper($fallback);
    }

    private function darkenHex(string $hex, float $amount): string
    {
        return $this->mixHexColor($hex, '#000000', $amount);
    }

    private function lightenHex(string $hex, float $amount): string
    {
        return $this->mixHexColor($hex, '#FFFFFF', $amount);
    }

    private function mixHexColor(string $hexA, string $hexB, float $amount): string
    {
        $amount = max(0.0, min(1.0, $amount));

        [$ar, $ag, $ab] = $this->hexToRgb($hexA);
        [$br, $bg, $bb] = $this->hexToRgb($hexB);

        $r = (int) round($ar * (1 - $amount) + $br * $amount);
        $g = (int) round($ag * (1 - $amount) + $bg * $amount);
        $b = (int) round($ab * (1 - $amount) + $bb * $amount);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function hexToRgb(string $hex): array
    {
        $clean = ltrim($hex, '#');

        return [
            hexdec(substr($clean, 0, 2)),
            hexdec(substr($clean, 2, 2)),
            hexdec(substr($clean, 4, 2)),
        ];
    }

    private function guessImageMimeType(string $content): string
    {
        if (str_starts_with($content, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($content, "\x89PNG")) {
            return 'image/png';
        }

        if (str_starts_with($content, "RIFF") && str_contains(substr($content, 0, 64), 'WEBP')) {
            return 'image/webp';
        }

        return 'image/png';
    }
}
