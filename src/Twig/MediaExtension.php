<?php

declare(strict_types=1);

namespace App\Twig;

use App\Infrastructure\Storage\StorageInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MediaExtension extends AbstractExtension
{
    public function __construct(
        private readonly StorageInterface $storage,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('media_url', [$this, 'getMediaUrl']),
            new TwigFunction('media_thumb_url', [$this, 'getMediaThumbUrl']),
        ];
    }

    public function getMediaUrl(string $storagePath): string
    {
        return $this->storage->getPublicUrl($storagePath);
    }

    public function getMediaThumbUrl(string $storagePath): string
    {
        // Convert media/{uuid}/{file}.ext → media/{uuid}/thumbs/{file}.ext
        $parts = pathinfo($storagePath);
        $dir = $parts['dirname'] ?? '';
        $basename = $parts['basename'] ?? $storagePath;

        $thumbPath = $dir . '/thumbs/' . $basename;

        return $this->storage->getPublicUrl($thumbPath);
    }
}
