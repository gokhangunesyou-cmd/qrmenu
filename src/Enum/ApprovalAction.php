<?php

namespace App\Enum;

enum ApprovalAction: string
{
    case SUBMITTED = 'SUBMITTED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
}
