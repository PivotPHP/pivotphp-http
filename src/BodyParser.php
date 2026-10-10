<?php

declare(strict_types=1);

namespace PivotPHP\Http;

use PivotPHP\Http\Exception\MalformedJsonException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Populates a request's parsed body from its raw body.
 *
 * PHP only fills `$_POST` for `POST` requests, so `application/json` bodies and
 * form bodies on `PUT`/`PATCH`/`DELETE` would otherwise be lost. This parser:
 *
 * - decodes `application/json` and `application/*+json` bodies (any method);
 * - parses `application/x-www-form-urlencoded` bodies for the configured
 *   methods (default: `PUT`, `PATCH`, `DELETE`).
 *
 * A request whose parsed body is already populated (non-null, non-empty) is
 * returned unchanged. Malformed JSON throws {@see MalformedJsonException}
 * (which carries the 400 status via {@see Exception\HttpExceptionInterface}).
 *
 * A JSON body that decodes to a scalar or `null` (e.g. `42`, `"text"`) yields
 * an empty parsed body — the parsed body is a keyed structure and cannot hold a
 * scalar; this is not treated as an error.
 *
 * Used by {@see Middleware\BodyParsingMiddleware}.
 */
final class BodyParser
{
    /** @var array<int, string> */
    private array $formMethods;

    /**
     * @param array<int, string> $formMethods Methods for which form bodies are
     *        parsed (PHP does not fill `$_POST` for these).
     */
    public function __construct(array $formMethods = ['PUT', 'PATCH', 'DELETE'])
    {
        $this->formMethods = array_map('strtoupper', $formMethods);
    }

    /**
     * @throws MalformedJsonException
     */
    public function parse(ServerRequestInterface $request): ServerRequestInterface
    {
        $parsed = $request->getParsedBody();

        if ($parsed !== null && $parsed !== []) {
            return $request;
        }

        $contentType = $request->getHeaderLine('Content-Type');

        if ($this->isJson($contentType)) {
            return $request->withParsedBody($this->parseJson($request));
        }

        if (
            str_contains(strtolower($contentType), 'application/x-www-form-urlencoded')
            && in_array(strtoupper($request->getMethod()), $this->formMethods, true)
        ) {
            return $request->withParsedBody($this->parseForm($request));
        }

        return $request;
    }

    private function isJson(string $contentType): bool
    {
        $type = strtolower(trim(explode(';', $contentType, 2)[0]));

        return $type === 'application/json' || str_ends_with($type, '+json');
    }

    /**
     * @return array<array-key, mixed>
     * @throws MalformedJsonException
     */
    private function parseJson(ServerRequestInterface $request): array
    {
        $body = (string) $request->getBody();

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
     * @return array<array-key, mixed>
     */
    private function parseForm(ServerRequestInterface $request): array
    {
        parse_str((string) $request->getBody(), $parsed);

        return $parsed;
    }
}
