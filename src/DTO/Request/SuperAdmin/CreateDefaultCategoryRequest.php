<?php

namespace App\DTO\Request\SuperAdmin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateDefaultCategoryRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 150)]
        public string $name,

        public ?string $description = null,
        public ?string $icon = null,
    ) {
    }
}
