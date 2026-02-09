<?php

namespace App\DTO\Response\SuperAdmin;

final readonly class RestaurantListItemResponse
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $slug,
        public bool $isActive,
        public int $productCount,
        public string $createdAt,
    ) {
    }
}
