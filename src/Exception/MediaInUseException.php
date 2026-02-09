<?php

namespace App\Exception;

class MediaInUseException extends \RuntimeException
{
    public function __construct(string $uuid)
    {
        parent::__construct(sprintf('Media "%s" is currently in use and cannot be deleted.', $uuid));
    }
}
