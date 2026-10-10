<?php

declare(strict_types=1);

namespace PivotPHP\Http\Factory;

use Nyholm\Psr7\Factory\Psr17Factory as BaseFactory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-17 factory for PivotPHP.
 *
 * Wraps nyholm/psr7's Psr17Factory so the framework depends on a single,
 * swappable entry point instead of the underlying implementation directly.
 * Produces standard PSR-7 messages, which the Express facade then wraps.
 */
final class Psr17Factory implements
    RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface
{
    private BaseFactory $inner;

    public function __construct(?BaseFactory $inner = null)
    {
        $this->inner = $inner ?? new BaseFactory();
    }

    public function createRequest(string $method, $uri): RequestInterface
    {
        return $this->inner->createRequest($method, $uri);
    }

    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return 2 > \func_num_args()
            ? $this->inner->createResponse($code)
            : $this->inner->createResponse($code, $reasonPhrase);
    }

    /**
     * @param array<array-key, mixed> $serverParams
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return $this->inner->createServerRequest($method, $uri, $serverParams);
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return $this->inner->createStream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return $this->inner->createStreamFromFile($filename, $mode);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        return $this->inner->createStreamFromResource($resource);
    }

    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = \UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ): UploadedFileInterface {
        return $this->inner->createUploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }

    public function createUri(string $uri = ''): UriInterface
    {
        return $this->inner->createUri($uri);
    }
}
