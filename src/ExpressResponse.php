<?php

declare(strict_types=1);

namespace PivotPHP\Http;

use Nyholm\Psr7\Response as Psr7Response;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Express.js-style facade over a PSR-7 {@see ResponseInterface}.
 *
 * Setters (`status()`, `header()`, `cookie()`) are fluent — they mutate the
 * wrapped PSR-7 message and return `$this`. Terminal methods (`json()`,
 * `text()`, `html()`, `redirect()`, `send()`, `noContent()`, `error()`) also
 * update the wrapped message (so {@see self::psr7()} reflects the final state
 * even without capturing the return value) and return the final
 * `ResponseInterface` ready to be emitted.
 *
 * ```php
 * return $res->status(404)->header('X-App', 'PivotPHP')->json(['error' => 'nope']);
 * ```
 */
final class ExpressResponse
{
    private ResponseInterface $psr7;

    public function __construct(?ResponseInterface $psr7 = null)
    {
        $this->psr7 = $psr7 ?? new Psr7Response();
    }

    /**
     * Returns the wrapped PSR-7 response.
     */
    public function psr7(): ResponseInterface
    {
        return $this->psr7;
    }

    public function status(int $code): self
    {
        $this->psr7 = $this->psr7->withStatus($code);

        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->psr7 = $this->psr7->withHeader($name, $value);

        return $this;
    }

    /**
     * Appends a `Set-Cookie` header.
     *
     * Attribute values are sanitized against header injection, and
     * `SameSite=None` forces `Secure` (browsers require it).
     *
     * @param array<string, mixed> $options Supports `expires` (DateTimeInterface
     *        or int timestamp), `maxAge` (int), `path`, `domain`, `secure`,
     *        `httpOnly` and `sameSite` (Lax|Strict|None).
     *
     * @throws \InvalidArgumentException On an invalid `expires` or `sameSite`.
     */
    public function cookie(string $name, string $value, array $options = []): self
    {
        $cookie = urlencode($name) . '=' . urlencode($value);

        if (isset($options['expires'])) {
            $cookie .= '; Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $this->timestampOf($options['expires']));
        }
        if (isset($options['maxAge']) && is_int($options['maxAge'])) {
            $cookie .= '; Max-Age=' . $options['maxAge'];
        }
        if (isset($options['path']) && is_string($options['path'])) {
            $cookie .= '; Path=' . $this->assertCookieAttribute($options['path'], 'path');
        }
        if (isset($options['domain']) && is_string($options['domain'])) {
            $cookie .= '; Domain=' . $this->assertCookieAttribute($options['domain'], 'domain');
        }

        $sameSite = $this->sameSiteOf($options);
        $secure = ($options['secure'] ?? false) === true || $sameSite === 'None';

        if ($secure) {
            $cookie .= '; Secure';
        }
        if (($options['httpOnly'] ?? false) === true) {
            $cookie .= '; HttpOnly';
        }
        if ($sameSite !== null) {
            $cookie .= '; SameSite=' . $sameSite;
        }

        $this->psr7 = $this->psr7->withAddedHeader('Set-Cookie', $cookie);

        return $this;
    }

    /**
     * Sends a JSON response.
     *
     * When `$status` is null the status set via {@see self::status()} (if any)
     * is kept.
     *
     * @throws \JsonException When the payload cannot be encoded.
     */
    public function json(mixed $data, ?int $status = null, int $flags = 0): ResponseInterface
    {
        $body = Stream::create((string) json_encode($data, $flags | JSON_THROW_ON_ERROR));

        return $this->withBody($status, 'application/json; charset=utf-8', $body);
    }

    /**
     * Sends a plain-text response.
     */
    public function text(string $text, ?int $status = null): ResponseInterface
    {
        return $this->withBody($status, 'text/plain; charset=utf-8', Stream::create($text));
    }

    /**
     * Sends an HTML response.
     */
    public function html(string $html, ?int $status = null): ResponseInterface
    {
        return $this->withBody($status, 'text/html; charset=utf-8', Stream::create($html));
    }

    /**
     * Sends a redirect.
     */
    public function redirect(string $url, int $status = 302): ResponseInterface
    {
        return $this->assign($this->psr7->withStatus($status)->withHeader('Location', $url));
    }

    /**
     * Sends an empty response (default 204 No Content).
     */
    public function noContent(int $status = 204): ResponseInterface
    {
        return $this->assign($this->psr7->withStatus($status));
    }

    /**
     * Sends `$data`: arrays/objects as JSON, everything else as text.
     */
    public function send(mixed $data, ?int $status = null): ResponseInterface
    {
        if (is_array($data) || is_object($data)) {
            return $this->json($data, $status);
        }

        if (is_scalar($data)) {
            $text = is_bool($data) ? ($data ? 'true' : 'false') : (string) $data;

            return $this->text($text, $status);
        }

        return $this->text('', $status);
    }

    /**
     * Sends a JSON error response, e.g. `{"error": "..."}`.
     *
     * @throws \JsonException When the payload cannot be encoded.
     */
    public function error(int $status, string $message = ''): ResponseInterface
    {
        return $this->json(['error' => $message], $status);
    }

    private function withBody(?int $status, string $contentType, StreamInterface $body): ResponseInterface
    {
        $response = $this->psr7
            ->withHeader('Content-Type', $contentType)
            ->withBody($body);

        return $this->assign($status !== null ? $response->withStatus($status) : $response);
    }

    private function assign(ResponseInterface $response): ResponseInterface
    {
        $this->psr7 = $response;

        return $response;
    }

    /**
     * @param mixed $expires
     */
    private function timestampOf(mixed $expires): int
    {
        if ($expires instanceof \DateTimeInterface) {
            return $expires->getTimestamp();
        }

        if (is_int($expires)) {
            return $expires;
        }

        throw new \InvalidArgumentException('Cookie "expires" must be a DateTimeInterface or an int timestamp.');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function sameSiteOf(array $options): ?string
    {
        $value = $options['sameSite'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException('Cookie "sameSite" must be a string.');
        }

        $sameSite = ucfirst(strtolower($value));

        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new \InvalidArgumentException('Cookie "sameSite" must be Lax, Strict or None.');
        }

        return $sameSite;
    }

    private function assertCookieAttribute(string $value, string $name): string
    {
        if (preg_match('/[;,\r\n\x00]/', $value) === 1) {
            throw new \InvalidArgumentException(
                sprintf('Cookie "%s" must not contain ";", ",", or control characters.', $name)
            );
        }

        return trim($value);
    }
}
