<?php

namespace App\Services\Ai;

use RuntimeException;

class AiServiceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'ai_service_error',
        public readonly ?int $statusCode = null,
    ) {
        parent::__construct($message);
    }
}
