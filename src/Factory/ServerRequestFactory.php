<?php

declare(strict_types=1);

namespace PivotPHP\Http\Factory;

use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Creates a PSR-7 server request from the PHP SAPI environment.
 *
 * Wraps nyholm/psr7-server's ServerRequestCreator: `fromGlobals()` builds a
 * request from the PHP superglobals, while `fromArrays()` builds one from plain
 * arrays — useful for tests and long-running runtimes (Swoole, RoadRunner).
 *
 * This factory only **builds** the request; it does not parse the body. Body
 * parsing happens inside the request pipeline via
 * {@see \PivotPHP\Http\Middleware\BodyParsingMiddleware}, so that a malformed
 * body throws within the pipeline (where it can be turned into a 400) instead
 * of before any handler exists. Wrap the result with
 * {@see \PivotPHP\Http\ExpressRequest} for the Express-style API.
 */
final class ServerRequestFactory
{
    public static function fromGlobals(): ServerRequestInterface
    {
        return self::creator()->fromGlobals();
    }

    /**
     * @param array<string, mixed>      $server
     * @param array<string, mixed>      $headers
     * @param array<string, mixed>      $cookie
     * @param array<string, mixed>      $get
     * @param array<string, mixed>|null $post
     * @param array<string, mixed>      $files
     */
    public static function fromArrays(
        array $server,
        array $headers = [],
        array $cookie = [],
        array $get = [],
        ?array $post = null,
        array $files = [],
        StreamInterface|string|null $body = null
    ): ServerRequestInterface {
        $mergedHeaders = array_merge(ServerRequestCreator::getHeadersFromServer($server), $headers);

        return self::creator()->fromArrays($server, $mergedHeaders, $cookie, $get, $post, $files, $body);
    }

    private static function creator(): ServerRequestCreator
    {
        $psr17 = new Psr17Factory();

        return new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
    }
}
