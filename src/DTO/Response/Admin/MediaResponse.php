<?php

namespace App\DTO\Response\Admin;

final readonly class MediaResponse
{
    public function __construct(
        public string $uuid,
        public string $originalFilename,
        public string $url,
        public string $mimeType,
        public int $fileSize,
        public ?int $width,
        public ?int $height,
    ) {
    }
}
