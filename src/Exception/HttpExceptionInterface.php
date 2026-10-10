<?php

declare(strict_types=1);

namespace PivotPHP\Http\Exception;

use Throwable;

/**
 * An exception that maps to an HTTP status code.
 *
 * Frameworks can catch this interface and use {@see getStatusCode()} to build
 * the error response, without knowing every concrete exception of this package.
 */
interface HttpExceptionInterface extends Throwable
{
    /**
     * The HTTP status code that should be returned for this exception.
     */
    public function getStatusCode(): int;
}
