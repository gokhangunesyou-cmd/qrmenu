<?php

declare(strict_types=1);

namespace App\Service\Image;

final class ProcessedImage
{
    public function __construct(
        private readonly string $contents,
        private readonly string $mimeType,
        private readonly int $width,
        private readonly int $height,
    ) {
    }

    public function getContents(): string
    {
        return $this->contents;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getSize(): int
    {
        return strlen($this->contents);
    }
}
