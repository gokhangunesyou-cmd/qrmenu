<?php

namespace App\DTO\Request\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCategoryRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 150)]
        public string $name,

        public ?string $description = null,
        public ?string $icon = null,
        public ?string $imageMediaUuid = null,
    ) {
    }
}
