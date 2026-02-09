<?php

namespace App\DTO\Response\SuperAdmin;

final readonly class RestaurantDetailResponse
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $slug,
        public string $ownerEmail,
        public string $ownerName,
        public bool $isActive,
        public string $themeSlug,
        public string $createdAt,
    ) {
    }
}
