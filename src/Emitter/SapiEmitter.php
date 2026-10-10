<?php

declare(strict_types=1);

namespace PivotPHP\Http\Emitter;

use Psr\Http\Message\ResponseInterface;

/**
 * Emits a PSR-7 response to the PHP SAPI.
 *
 * - Sends the status line and headers once, before any body output.
 * - Streams the body in chunks instead of casting it to a string.
 * - Omits the body for `HEAD` requests and for statuses that must not carry a
 *   body (`1xx`, `204`, `304`).
 * - Sets `Content-Length` when the body size is known and not already set.
 *
 * ```php
 * (new SapiEmitter())->emit($response, $request->getMethod());
 * ```
 */
final class SapiEmitter
{
    private int $bufferLength;

    public function __construct(int $bufferLength = 8192)
    {
        $this->bufferLength = $bufferLength;
    }

    /**
     * @param string|null $requestMethod Request method; defaults to
     *        `$_SERVER['REQUEST_METHOD']` (or `GET`).
     *
     * @throws \RuntimeException When the headers have already been sent.
     */
    public function emit(ResponseInterface $response, ?string $requestMethod = null): void
    {
        if (headers_sent()) {
            throw new \RuntimeException('Unable to emit response: headers already sent.');
        }

        $status = $response->getStatusCode();

        http_response_code($status);
        $this->emitHeaders($response);

        if (!$this->hasBody($this->methodOf($requestMethod), $status)) {
            return;
        }

        $size = $response->getBody()->getSize();

        if ($size !== null && !$response->hasHeader('Content-Length')) {
            header('Content-Length: ' . $size, true);
        }

        $this->emitBody($response);
    }

    private function methodOf(?string $requestMethod): string
    {
        if ($requestMethod !== null) {
            return $requestMethod;
        }

        $serverMethod = $_SERVER['REQUEST_METHOD'] ?? null;

        return is_string($serverMethod) ? $serverMethod : 'GET';
    }

    private function hasBody(string $method, int $status): bool
    {
        return strtoupper($method) !== 'HEAD'
            && $status !== 204
            && $status !== 304
            && !($status >= 100 && $status < 200);
    }

    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            $replace = true;
            foreach ($values as $value) {
                header($name . ': ' . $value, $replace);
                $replace = false;
            }
        }
    }

    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            $chunk = $body->read($this->bufferLength);

            if ($chunk === '') {
                break;
            }

            echo $chunk;
        }
    }
}
