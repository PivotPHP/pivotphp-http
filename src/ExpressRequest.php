<?php

declare(strict_types=1);

namespace PivotPHP\Http;

use PivotPHP\Http\Exception\MalformedJsonException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Express.js-style facade over a PSR-7 {@see ServerRequestInterface}.
 *
 * The PSR-7 request is the underlying representation; this class only adds the
 * ergonomic accessors a Laravel/Express-inspired API expects (`param()`,
 * `query()`, `input()`, `json()`, ...).
 *
 * ## Route parameter contract
 *
 * The router is expected to store the matched route parameters in the request
 * attribute named {@see self::ROUTE_PARAMS_ATTRIBUTE} (`route_params`) as an
 * associative `array<string, string>`. {@see self::param()} and
 * {@see self::params()} read from it:
 *
 * ```php
 * $request = $request->withAttribute(
 *     ExpressRequest::ROUTE_PARAMS_ATTRIBUTE,
 *     ['id' => '42'] // matched from /users/:id
 * );
 * ```
 *
 * This request view is read-only; the wrapped message stays immutable.
 */
final class ExpressRequest
{
    /**
     * Request attribute holding the matched route parameters.
     */
    public const ROUTE_PARAMS_ATTRIBUTE = 'route_params';

    private ServerRequestInterface $psr7;

    public function __construct(ServerRequestInterface $psr7)
    {
        $this->psr7 = $psr7;
    }

    /**
     * Returns the underlying PSR-7 request.
     */
    public function psr7(): ServerRequestInterface
    {
        return $this->psr7;
    }

    public function method(): string
    {
        return $this->psr7->getMethod();
    }

    public function path(): string
    {
        return $this->psr7->getUri()->getPath();
    }

    /**
     * Returns all matched route parameters.
     *
     * @return array<array-key, mixed>
     */
    public function params(): array
    {
        $params = $this->psr7->getAttribute(self::ROUTE_PARAMS_ATTRIBUTE);

        return is_array($params) ? $params : [];
    }

    /**
     * Returns a route parameter, or the given default.
     */
    public function param(string $name, mixed $default = null): mixed
    {
        $params = $this->params();

        return array_key_exists($name, $params) ? $params[$name] : $default;
    }

    /**
     * Returns a query-string value, or the given default.
     */
    public function query(string $name, mixed $default = null): mixed
    {
        $query = $this->psr7->getQueryParams();

        return array_key_exists($name, $query) ? $query[$name] : $default;
    }

    /**
     * Returns a value from the parsed body, then from the query string, then
     * the given default.
     *
     * The parsed body is populated by {@see Middleware\BodyParsingMiddleware}
     * (or by PHP for `POST` form bodies); JSON and `PUT`/`PATCH`/`DELETE` form
     * bodies therefore require that middleware to be in the pipeline.
     */
    public function input(string $name, mixed $default = null): mixed
    {
        $body = $this->parsedBody();

        return array_key_exists($name, $body) ? $body[$name] : $this->query($name, $default);
    }

    /**
     * Decodes the request body as JSON.
     *
     * A JSON object decodes to an associative array and a JSON array to a
     * list. An empty body yields an empty array; a malformed non-empty body
     * throws {@see MalformedJsonException}.
     *
     * @return array<array-key, mixed>
     * @throws MalformedJsonException
     */
    public function json(): array
    {
        $body = (string) $this->psr7->getBody();

        if ($body === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new MalformedJsonException($e->getMessage(), 0, $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Returns a header line, or the given default when absent.
     */
    public function header(string $name, ?string $default = null): ?string
    {
        $line = $this->psr7->getHeaderLine($name);

        return $line !== '' ? $line : $default;
    }

    /**
     * Returns a cookie value, or the given default.
     */
    public function cookie(string $name, ?string $default = null): ?string
    {
        $cookies = $this->psr7->getCookieParams();
        $value = $cookies[$name] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * Returns an uploaded file by field name.
     */
    public function file(string $name): ?UploadedFileInterface
    {
        $files = $this->psr7->getUploadedFiles();
        $file = $files[$name] ?? null;

        return $file instanceof UploadedFileInterface ? $file : null;
    }

    /**
     * Returns the client IP address (from server params), if available.
     */
    public function ip(): ?string
    {
        $server = $this->psr7->getServerParams();
        $ip = $server['REMOTE_ADDR'] ?? null;

        return is_string($ip) ? $ip : null;
    }

    public function userAgent(?string $default = null): ?string
    {
        return $this->header('User-Agent', $default);
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With') ?? '') === 'xmlhttprequest';
    }

    public function isJson(): bool
    {
        return str_contains(strtolower($this->header('Content-Type') ?? ''), 'application/json');
    }

    public function isSecure(): bool
    {
        $https = $this->psr7->getServerParams()['HTTPS'] ?? null;

        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }

    /**
     * Whether the request accepts the given media type, honouring the `q`
     * parameter of the `Accept` header. The most specific matching range wins;
     * a matching range with `q=0` means "not acceptable".
     */
    public function accepts(string $type): bool
    {
        $accept = $this->header('Accept');

        if ($accept === null || trim($accept) === '') {
            return true;
        }

        [$major, $minor] = $this->splitMediaType($type);

        $bestSpecificity = -1;
        $bestQuality = 0.0;

        foreach (explode(',', $accept) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            $parts = explode(';', $entry);
            [$rangeMajor, $rangeMinor] = $this->splitMediaType(trim($parts[0]));

            if ($rangeMajor !== '*' && ($rangeMajor !== $major || ($rangeMinor !== '*' && $rangeMinor !== $minor))) {
                continue;
            }

            $specificity = match (true) {
                $rangeMajor === '*' => 0,
                $rangeMinor === '*' => 1,
                default => 2,
            };

            if ($specificity > $bestSpecificity) {
                $bestSpecificity = $specificity;
                $bestQuality = $this->qualityOf($parts);
            }
        }

        return $bestQuality > 0.0;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function parsedBody(): array
    {
        $parsed = $this->psr7->getParsedBody();

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * @param array<int, string> $parts
     */
    private function qualityOf(array $parts): float
    {
        foreach (array_slice($parts, 1) as $param) {
            $param = trim($param);

            if (str_starts_with($param, 'q=')) {
                return (float) substr($param, 2);
            }
        }

        return 1.0;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitMediaType(string $type): array
    {
        $parts = explode('/', strtolower(trim($type)), 2);

        return [$parts[0], $parts[1] ?? '*'];
    }
}
