<?php

namespace App\Exceptions;

use RuntimeException;

class DomainRuleException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function toArray(): array
    {
        return array_merge([
            'success' => false,
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
        ], $this->context);
    }
}
