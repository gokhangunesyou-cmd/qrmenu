<?php

namespace App\DTO\Response\Admin;

final readonly class PresignResponse
{
    public function __construct(
        public string $mediaUuid,
        public string $uploadUrl,
        public string $expiresAt,
    ) {
    }
}
