<?php

namespace App\DTO\Request\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateRestaurantRequest
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?string $phone = null,

        #[Assert\Email]
        public ?string $email = null,

        public ?string $address = null,
        public ?string $city = null,

        #[Assert\Length(max: 2)]
        public ?string $countryCode = null,

        public ?int $themeId = null,
        public ?string $defaultLocale = null,

        #[Assert\Length(max: 3)]
        public ?string $currencyCode = null,

        public ?array $colorOverrides = null,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $logoMediaUuid = null,
        public ?string $coverMediaUuid = null,
    ) {
    }
}
