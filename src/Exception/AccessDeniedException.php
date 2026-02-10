<?php

namespace App\Exception;

class AccessDeniedException extends \RuntimeException
{
    public function __construct(string $message = 'Access denied.')
    {
        parent::__construct($message);
    }
}
