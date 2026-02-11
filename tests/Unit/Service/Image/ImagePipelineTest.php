<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Image;

use App\Service\Image\ImagePipeline;
use PHPUnit\Framework\TestCase;

final class ImagePipelineTest extends TestCase
{
    private ImagePipeline $pipeline;

    protected function setUp(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD extension is required for image pipeline tests.');
        }

        $this->pipeline = new ImagePipeline();
    }

    public function testCatalogProfileResizesLargeJpeg(): void
    {
        $source = imagecreatetruecolor(2200, 1200);
        self::assertNotFalse($source);
        $fill = imagecolorallocate($source, 30, 120, 200);
        imagefilledrectangle($source, 0, 0, 2200, 1200, $fill);

        ob_start();
        imagejpeg($source, null, 95);
        $payload = ob_get_clean();
        imagedestroy($source);

        self::assertIsString($payload);

        $processed = $this->pipeline->processBinary($payload, 'image/jpeg', ImagePipeline::PROFILE_CATALOG);

        self::assertSame(1600, $processed->getWidth());
        self::assertSame(873, $processed->getHeight());
        self::assertSame('image/jpeg', $processed->getMimeType());
        self::assertGreaterThan(0, $processed->getSize());
    }

    public function testLogoProfileDoesNotUpscaleSmallPng(): void
    {
        $source = imagecreatetruecolor(120, 80);
        self::assertNotFalse($source);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        $fill = imagecolorallocatealpha($source, 0, 0, 0, 127);
        imagefilledrectangle($source, 0, 0, 120, 80, $fill);

        ob_start();
        imagepng($source, null, 6);
        $payload = ob_get_clean();
        imagedestroy($source);

        self::assertIsString($payload);

        $processed = $this->pipeline->processBinary($payload, 'image/png', ImagePipeline::PROFILE_LOGO);

        self::assertSame(120, $processed->getWidth());
        self::assertSame(80, $processed->getHeight());
        self::assertSame('image/png', $processed->getMimeType());
        self::assertGreaterThan(0, $processed->getSize());
    }
}
