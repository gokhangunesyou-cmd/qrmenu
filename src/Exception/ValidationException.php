<?php

namespace App\Exception;

class ValidationException extends \RuntimeException
{
    /** @var array<array{field: string, message: string}> */
    private array $errors;

    /**
     * @param array<array{field: string, message: string}> $errors
     */
    public function __construct(array $errors, string $message = 'Validation failed.')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /** @return array<array{field: string, message: string}> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
