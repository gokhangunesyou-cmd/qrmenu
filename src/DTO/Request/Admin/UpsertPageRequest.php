<?php

namespace App\DTO\Request\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpsertPageRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $title,

        public ?string $body = null,
        public ?bool $isPublished = null,
    ) {
    }
}
