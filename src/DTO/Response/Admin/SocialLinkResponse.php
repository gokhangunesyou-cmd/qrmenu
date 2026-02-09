<?php

namespace App\DTO\Response\Admin;

final readonly class SocialLinkResponse
{
    public function __construct(
        public string $platform,
        public string $url,
        public int $sortOrder,
    ) {
    }
}
