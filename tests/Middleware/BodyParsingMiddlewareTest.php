<?php

declare(strict_types=1);

namespace PivotPHP\Http\Tests\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use PivotPHP\Http\Exception\MalformedJsonException;
use PivotPHP\Http\Middleware\BodyParsingMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BodyParsingMiddlewareTest extends TestCase
{
    /**
     * A handler that encodes the parsed body into the response, so the
     * middleware's effect can be asserted from the returned response.
     */
    private function echoingHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], (string) json_encode($request->getParsedBody()));
            }
        };
    }

    /**
     * @param array<string, string> $headers
     */
    private function jsonRequest(string $contentType, string $body): ServerRequestInterface
    {
        return new ServerRequest('POST', '/', ['Content-Type' => $contentType], $body);
    }

    public function testParsesJsonBodyOnPost(): void
    {
        $response = (new BodyParsingMiddleware())
            ->process($this->jsonRequest('application/json', '{"name":"a"}'), $this->echoingHandler());

        $this->assertSame('{"name":"a"}', (string) $response->getBody());
    }

    public function testParsesJsonVendorSuffix(): void
    {
        $response = (new BodyParsingMiddleware())
            ->process($this->jsonRequest('application/vnd.api+json', '{"name":"a"}'), $this->echoingHandler());

        $this->assertSame('{"name":"a"}', (string) $response->getBody());
    }

    public function testParsesJsonOnPut(): void
    {
        $request = new ServerRequest('PUT', '/', ['Content-Type' => 'application/json'], '[1,2]');

        $response = (new BodyParsingMiddleware())->process($request, $this->echoingHandler());

        $this->assertSame('[1,2]', (string) $response->getBody());
    }

    public function testParsesJsonWhenParsedBodyIsEmptyArray(): void
    {
        $request = $this->jsonRequest('application/json', '{"name":"a"}')->withParsedBody([]);

        $response = (new BodyParsingMiddleware())->process($request, $this->echoingHandler());

        $this->assertSame('{"name":"a"}', (string) $response->getBody());
    }

    public function testScalarJsonYieldsEmptyParsedBody(): void
    {
        $response = (new BodyParsingMiddleware())
            ->process($this->jsonRequest('application/json', '42'), $this->echoingHandler());

        $this->assertSame('[]', (string) $response->getBody());
    }

    public function testThrowsOnInvalidJson(): void
    {
        $this->expectException(MalformedJsonException::class);

        (new BodyParsingMiddleware())
            ->process($this->jsonRequest('application/json', 'not-json'), $this->echoingHandler());
    }

    public function testMalformedJsonCarries400Status(): void
    {
        try {
            (new BodyParsingMiddleware())
                ->process($this->jsonRequest('application/json', 'not-json'), $this->echoingHandler());
            $this->fail('Expected MalformedJsonException');
        } catch (MalformedJsonException $e) {
            $this->assertSame(400, $e->getStatusCode());
        }
    }

    public function testParsesFormBodyOnPut(): void
    {
        $request = new ServerRequest(
            'PUT',
            '/',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'name=a&x=1'
        );

        $response = (new BodyParsingMiddleware())->process($request, $this->echoingHandler());

        $this->assertSame('{"name":"a","x":"1"}', (string) $response->getBody());
    }

    public function testLeavesAlreadyParsedBodyUntouched(): void
    {
        $request = $this->jsonRequest('application/json', '{"name":"a"}')
            ->withParsedBody(['name' => 'custom']);

        $response = (new BodyParsingMiddleware())->process($request, $this->echoingHandler());

        $this->assertSame('{"name":"custom"}', (string) $response->getBody());
    }

    public function testDoesNothingWithoutParsableBody(): void
    {
        $request = new ServerRequest('GET', '/');

        $response = (new BodyParsingMiddleware())->process($request, $this->echoingHandler());

        $this->assertSame('null', (string) $response->getBody());
    }
}
