<?php

namespace App\DTO\Response\Public;

final readonly class PageResponse
{
    public function __construct(
        public string $type,
        public string $title,
        public ?string $body,
    ) {
    }
}
