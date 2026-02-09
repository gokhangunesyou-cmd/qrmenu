<?php

namespace App\DTO\Request\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateProductRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $name,

        public ?string $description = null,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public string $price,

        #[Assert\NotBlank]
        public string $categoryUuid,

        public ?bool $isFeatured = null,
        public ?array $allergens = null,
        public ?array $imageMediaUuids = null,
    ) {
    }
}
