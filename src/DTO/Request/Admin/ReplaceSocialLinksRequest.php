<?php

namespace App\DTO\Request\Admin;

final readonly class ReplaceSocialLinksRequest
{
    public function __construct(
        public array $links,
    ) {
    }
}
