<?php

namespace App\Message;

class CleanOrphanedMediaMessage
{
    public function __construct(
        public readonly int $daysOld = 30,
    ) {
    }
}
