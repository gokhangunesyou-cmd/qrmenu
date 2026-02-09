<?php

namespace App\DTO\Response\Admin;

final readonly class CatalogProductResponse
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $description,
        public array $images,
    ) {
    }
}
