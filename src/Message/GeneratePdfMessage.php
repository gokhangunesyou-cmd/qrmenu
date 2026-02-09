<?php

namespace App\Message;

class GeneratePdfMessage
{
    public function __construct(
        public readonly string $templateSlug,
        /** @var string[] */
        public readonly array $qrCodeUuids,
        public readonly int $restaurantId,
    ) {
    }
}
