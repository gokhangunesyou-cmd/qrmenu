<?php

namespace App\DTO\Response\SuperAdmin;

final readonly class ApprovalDetailResponse
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $description,
        public string $price,
        public string $restaurantName,
        public string $submitterName,
        public string $submittedAt,
        public array $images,
        public array $approvalHistory,
    ) {
    }
}
