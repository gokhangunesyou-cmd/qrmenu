<?php

namespace App\DTO\Response\Admin;

final readonly class PageResponse
{
    public function __construct(
        public string $type,
        public string $title,
        public ?string $body,
        public bool $isPublished,
    ) {
    }
}
