<?php

namespace App\DTO\Response\Admin;

final readonly class QrCodeResponse
{
    public function __construct(
        public string $uuid,
        public string $type,
        public ?int $tableNumber,
        public ?string $tableLabel,
        public string $encodedUrl,
        public string $imageUrl,
        public bool $isActive,
        public string $createdAt,
    ) {
    }
}
