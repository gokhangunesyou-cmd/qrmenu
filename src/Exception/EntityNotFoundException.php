<?php

namespace App\Exception;

class EntityNotFoundException extends \RuntimeException
{
    public function __construct(string $entityName, string $identifier)
    {
        parent::__construct(sprintf('%s with identifier "%s" not found.', $entityName, $identifier));
    }
}
