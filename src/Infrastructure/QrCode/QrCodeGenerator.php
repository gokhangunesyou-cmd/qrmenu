<?php

declare(strict_types=1);

namespace App\Infrastructure\QrCode;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeGenerator
{
    /**
     * Generate a QR code PNG image as binary string.
     */
    public function generate(
        string $url,
        int $size = 300,
        ?string $foregroundColor = null,
        ?string $backgroundColor = null,
        ?int $borderRadius = null,
        ?string $logoPath = null,
    ): string {
        return $this->buildQrCode($url, new PngWriter(), $size, $foregroundColor, $backgroundColor, $borderRadius, $logoPath);
    }

    /**
     * Generate a QR code SVG image as string.
     */
    public function generateSvg(
        string $url,
        int $size = 300,
        ?string $foregroundColor = null,
        ?string $backgroundColor = null,
        ?int $borderRadius = null,
    ): string {
        return $this->buildQrCode($url, new SvgWriter(), $size, $foregroundColor, $backgroundColor, $borderRadius);
    }

    private function buildQrCode(
        string $url,
        PngWriter|SvgWriter $writer,
        int $size,
        ?string $foregroundColor,
        ?string $backgroundColor,
        ?int $borderRadius,
        ?string $logoPath = null,
    ): string {
        $fgColor = $this->hexToColor($foregroundColor ?? '#000000');
        $bgColor = $this->hexToColor($backgroundColor ?? '#ffffff');

        $builder = Builder::create()
            ->writer($writer)
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size($size)
            ->margin(10)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->foregroundColor($fgColor)
            ->backgroundColor($bgColor);

        if ($logoPath !== null && file_exists($logoPath)) {
            $logoSize = (int) round($size * 0.2);
            $builder->logoPath($logoPath)
                ->logoResizeToWidth($logoSize)
                ->logoResizeToHeight($logoSize);
        }

        $result = $builder->build();

        return $result->getString();
    }

    private function hexToColor(string $hex): Color
    {
        $hex = ltrim($hex, '#');
        if (\strlen($hex) !== 6) {
            return new Color(0, 0, 0);
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return new Color((int) $r, (int) $g, (int) $b);
    }
}
