<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InvalidRequestException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly array $violations = [],
    ) {
        parent::__construct($message);
    }

    public function getViolations(): array
    {
        return $this->violations;
    }
}
