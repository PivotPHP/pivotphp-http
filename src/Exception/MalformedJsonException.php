<?php

declare(strict_types=1);

namespace PivotPHP\Http\Exception;

use RuntimeException;

/**
 * Thrown when a request body declared as JSON cannot be decoded.
 *
 * Implements {@see HttpExceptionInterface} so a framework can map it to a
 * `400 Bad Request` without knowing the concrete exception.
 */
final class MalformedJsonException extends RuntimeException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 400;
    }
}
