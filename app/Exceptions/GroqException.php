<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error al comunicarse con el proveedor de IA (Groq).
 * Clasifica la causa para que el frontend muestre el consejo correcto:
 * network | auth | rate_limit | provider | not_configured | empty.
 */
class GroqException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'provider',
        int $statusCode = 502,
    ) {
        parent::__construct($message, $statusCode);
    }
}
