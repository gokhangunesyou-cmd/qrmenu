<?php

namespace App\DTO\Response\Admin;

final readonly class RestaurantResponse
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $slug,
        public ?string $description,
        public ?string $phone,
        public ?string $email,
        public ?string $address,
        public ?string $city,
        public ?string $countryCode,
        public int $themeId,
        public string $defaultLocale,
        public string $currencyCode,
        public ?array $colorOverrides,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $logoUrl,
        public ?string $coverUrl,
        public bool $isActive,
        public string $createdAt,
    ) {
    }
}
