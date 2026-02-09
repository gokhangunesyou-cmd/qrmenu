<?php

namespace App\DTO\Request\SuperAdmin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateRestaurantRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Slug can only contain lowercase letters, numbers, and hyphens.')]
        public string $slug,

        #[Assert\NotBlank]
        #[Assert\Email]
        public string $ownerEmail,

        #[Assert\NotBlank]
        public string $ownerFirstName,

        #[Assert\NotBlank]
        public string $ownerLastName,

        #[Assert\NotBlank]
        #[Assert\Length(min: 8)]
        public string $ownerPassword,
    ) {
    }
}
