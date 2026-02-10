<?php

namespace App\DTO\Response\SuperAdmin;

final readonly class DefaultCategoryResponse
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $description,
        public ?string $icon,
        public int $sortOrder,
    ) {
    }
}
