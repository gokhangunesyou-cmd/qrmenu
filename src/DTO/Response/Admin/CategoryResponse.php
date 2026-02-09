<?php

namespace App\DTO\Response\Admin;

final readonly class CategoryResponse
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $description,
        public ?string $icon,
        public ?string $imageUrl,
        public int $sortOrder,
        public bool $isActive,
        public string $createdAt,
    ) {
    }
}
