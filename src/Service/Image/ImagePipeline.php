<?php

declare(strict_types=1);

namespace App\Service\Image;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImagePipeline
{
    public const PROFILE_LOGO = 'logo';
    public const PROFILE_COVER = 'cover';
    public const PROFILE_CATALOG = 'catalog';
    public const PROFILE_THUMB = 'thumb';

    /**
     * @var array<string, array{max_width:int,max_height:int,jpeg_quality:int,webp_quality:int,png_compression:int}>
     */
    private const PROFILES = [
        self::PROFILE_LOGO => [
            'max_width' => 512,
            'max_height' => 512,
            'jpeg_quality' => 90,
            'webp_quality' => 90,
            'png_compression' => 6,
        ],
        self::PROFILE_COVER => [
            'max_width' => 1600,
            'max_height' => 900,
            'jpeg_quality' => 84,
            'webp_quality' => 84,
            'png_compression' => 6,
        ],
        self::PROFILE_CATALOG => [
            'max_width' => 1600,
            'max_height' => 1600,
            'jpeg_quality' => 82,
            'webp_quality' => 82,
            'png_compression' => 6,
        ],
        self::PROFILE_THUMB => [
            'max_width' => 300,
            'max_height' => 300,
            'jpeg_quality' => 78,
            'webp_quality' => 78,
            'png_compression' => 7,
        ],
    ];

    public function processUploadedFile(UploadedFile $file, string $mimeType, string $profile): ProcessedImage
    {
        $source = file_get_contents($file->getPathname());
        if ($source === false) {
            throw new \RuntimeException('Uploaded image could not be read.');
        }

        return $this->processBinary($source, $mimeType, $profile);
    }

    public function processBinary(string $source, string $mimeType, string $profile): ProcessedImage
    {
        $config = self::PROFILES[$profile] ?? null;
        if ($config === null) {
            throw new \InvalidArgumentException(sprintf('Unknown image profile: "%s".', $profile));
        }

        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported mime type for processing: %s.', $mimeType));
        }

        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('GD extension is required for image processing.');
        }

        $image = @imagecreatefromstring($source);
        if ($image === false) {
            throw new \InvalidArgumentException('Image payload is invalid or unsupported.');
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($image);
            throw new \InvalidArgumentException('Image dimensions are invalid.');
        }

        $ratio = min(
            $config['max_width'] / $sourceWidth,
            $config['max_height'] / $sourceHeight,
            1.0,
        );

        $targetWidth = max(1, (int) round($sourceWidth * $ratio));
        $targetHeight = max(1, (int) round($sourceHeight * $ratio));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($target === false) {
            imagedestroy($image);
            throw new \RuntimeException('Image canvas could not be allocated.');
        }

        if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        } else {
            $background = imagecolorallocate($target, 255, 255, 255);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $background);
        }

        imagecopyresampled(
            $target,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );

        $encoded = $this->encodeImage($target, $mimeType, $config);

        imagedestroy($image);
        imagedestroy($target);

        if ($encoded === null) {
            throw new \RuntimeException('Image could not be encoded.');
        }

        return new ProcessedImage($encoded, $mimeType, $targetWidth, $targetHeight);
    }

    /**
     * @param array{max_width:int,max_height:int,jpeg_quality:int,webp_quality:int,png_compression:int} $config
     */
    private function encodeImage(\GdImage $image, string $mimeType, array $config): ?string
    {
        ob_start();
        $ok = match ($mimeType) {
            'image/jpeg' => function_exists('imagejpeg') ? imagejpeg($image, null, $config['jpeg_quality']) : false,
            'image/png' => function_exists('imagepng') ? imagepng($image, null, $config['png_compression']) : false,
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, null, $config['webp_quality']) : false,
            default => false,
        };
        $contents = ob_get_clean();

        if ($ok !== true || !is_string($contents) || $contents === '') {
            return null;
        }

        return $contents;
    }
}
