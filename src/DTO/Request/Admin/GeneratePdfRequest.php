<?php

namespace App\DTO\Request\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GeneratePdfRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $templateSlug,

        #[Assert\NotBlank]
        public array $qrCodeUuids,
    ) {
    }
}
