<?php

namespace App\DTO\Request\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PresignUploadRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $filename,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['image/jpeg', 'image/png', 'image/webp'])]
        public string $mimeType,
    ) {
    }
}
