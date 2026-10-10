<?php

declare(strict_types=1);

namespace PivotPHP\Http\Middleware;

use PivotPHP\Http\BodyParser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware that populates the request's parsed body.
 *
 * Delegates to {@see BodyParser}: decodes `application/json` bodies (any
 * method) and parses form bodies for the configured methods. Malformed JSON
 * throws {@see \PivotPHP\Http\Exception\MalformedJsonException} — map it to a
 * `400 Bad Request`.
 */
final class BodyParsingMiddleware implements MiddlewareInterface
{
    private BodyParser $parser;

    /**
     * @param array<int, string> $formMethods
     */
    public function __construct(array $formMethods = ['PUT', 'PATCH', 'DELETE'])
    {
        $this->parser = new BodyParser($formMethods);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($this->parser->parse($request));
    }
}
