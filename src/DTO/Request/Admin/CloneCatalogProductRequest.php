<?php

namespace App\DTO\Request\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CloneCatalogProductRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $categoryUuid,

        #[Assert\NotBlank]
        public string $price,
    ) {
    }
}
