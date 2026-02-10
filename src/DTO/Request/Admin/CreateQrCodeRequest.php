<?php

namespace App\DTO\Request\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateQrCodeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['restaurant', 'table'])]
        public string $type,

        public ?int $tableNumber = null,
        public ?string $tableLabel = null,
    ) {
    }
}
