<?php

namespace App\DTO\Response\SuperAdmin;

final readonly class PendingApprovalResponse
{
    public function __construct(
        public string $uuid,
        public string $productName,
        public string $restaurantName,
        public string $submittedAt,
        public ?string $imageUrl,
    ) {
    }
}
