<?php

namespace App\DTO\Response\Admin;

final readonly class ProductResponse
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $description,
        public string $price,
        public string $categoryUuid,
        public string $status,
        public bool $isFeatured,
        public bool $isActive,
        public ?array $allergens,
        public int $sortOrder,
        public array $images,
        public ?string $catalogProductUuid,
        public string $createdAt,
    ) {
    }
}
