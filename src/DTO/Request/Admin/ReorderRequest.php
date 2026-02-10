<?php

namespace App\DTO\Request\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReorderRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public array $uuids,

        public ?string $categoryUuid = null,
    ) {
    }
}
