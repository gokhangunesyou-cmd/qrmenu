<?php

namespace App\DTO\Request\SuperAdmin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateDefaultCategoryRequest
{
    public function __construct(
        #[Assert\Length(max: 150)]
        public ?string $name = null,

        public ?string $description = null,
        public ?string $icon = null,
    ) {
    }
}
