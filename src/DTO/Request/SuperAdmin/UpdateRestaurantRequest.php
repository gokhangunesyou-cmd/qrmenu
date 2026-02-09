<?php

namespace App\DTO\Request\SuperAdmin;

final readonly class UpdateRestaurantRequest
{
    public function __construct(
        public ?string $name = null,
        public ?string $slug = null,
        public ?bool $isActive = null,
    ) {
    }
}
