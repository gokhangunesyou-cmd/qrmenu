<?php

namespace App\DTO\Response\Public;

final readonly class MenuResponse
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
        public array $theme,
        public array $categories,
        public array $socialLinks,
        public array $pages,
        public string $currency,
        public string $locale,
        public ?string $logoUrl,
        public ?string $coverUrl,
        public ?string $metaTitle,
        public ?string $metaDescription,
    ) {
    }
}
