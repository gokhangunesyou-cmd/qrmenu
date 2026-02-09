<?php

namespace App\Enum;

enum ProductStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::DRAFT => $target === self::PENDING_APPROVAL,
            self::PENDING_APPROVAL => in_array($target, [self::APPROVED, self::REJECTED], true),
            self::REJECTED => in_array($target, [self::DRAFT, self::PENDING_APPROVAL], true),
            self::APPROVED => false,
        };
    }
}
