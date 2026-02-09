<?php

namespace App\DTO\Request\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateCategoryRequest
{
    public function __construct(
        #[Assert\Length(max: 150)]
        public ?string $name = null,

        public ?string $description = null,
        public ?string $icon = null,
        public ?string $imageMediaUuid = null,
        public ?bool $isActive = null,
    ) {
    }
}
