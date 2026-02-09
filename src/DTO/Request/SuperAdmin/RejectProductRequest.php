<?php

namespace App\DTO\Request\SuperAdmin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RejectProductRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $note,
    ) {
    }
}
