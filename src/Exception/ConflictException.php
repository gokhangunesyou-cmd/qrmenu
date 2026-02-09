<?php

namespace App\Exception;

class ConflictException extends \RuntimeException
{
    public function __construct(string $message = 'Resource conflict.')
    {
        parent::__construct($message);
    }
}
