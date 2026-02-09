<?php

namespace App\DTO\Request\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateProductRequest
{
    public function __construct(
        #[Assert\Length(max: 200)]
        public ?string $name = null,

        public ?string $description = null,
        public ?string $price = null,
        public ?string $categoryUuid = null,
        public ?bool $isFeatured = null,
        public ?bool $isActive = null,
        public ?array $allergens = null,
        public ?array $imageMediaUuids = null,
    ) {
    }
}
