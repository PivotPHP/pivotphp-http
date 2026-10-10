<?php

declare(strict_types=1);

namespace PivotPHP\Http\Tests\Factory;

use PHPUnit\Framework\TestCase;
use PivotPHP\Http\Factory\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface;

class ServerRequestFactoryTest extends TestCase
{
    public function testFromArrays(): void
    {
        $request = ServerRequestFactory::fromArrays(
            [
                'REQUEST_METHOD' => 'POST',
                'HTTP_HOST' => 'example.com',
                'REQUEST_URI' => '/users/42?page=2',
            ],
            [],
            ['session' => 'abc'],
            ['page' => '2'],
            ['name' => 'pivot']
        );

        $this->assertInstanceOf(ServerRequestInterface::class, $request);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/users/42', $request->getUri()->getPath());
        $this->assertSame(['page' => '2'], $request->getQueryParams());
        $this->assertSame(['name' => 'pivot'], $request->getParsedBody());
        $this->assertSame(['session' => 'abc'], $request->getCookieParams());
    }

    public function testFromArraysOnlyBuildsRequestAndDoesNotParseBody(): void
    {
        $request = ServerRequestFactory::fromArrays(
            [
                'REQUEST_METHOD' => 'POST',
                'HTTP_HOST' => 'example.com',
                'REQUEST_URI' => '/',
                'CONTENT_TYPE' => 'application/json',
            ],
            [],
            [],
            [],
            null,
            [],
            '{"name":"a"}'
        );

        // Content-Type is extracted from the server array...
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        // ...but the factory only builds the request; parsing is the middleware's job.
        $this->assertNull($request->getParsedBody());
    }

    public function testFromGlobals(): void
    {
        $server = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'localhost';

        try {
            $request = ServerRequestFactory::fromGlobals();

            $this->assertSame('GET', $request->getMethod());
            $this->assertSame('localhost', $request->getUri()->getHost());
        } finally {
            $_SERVER = $server;
        }
    }
}
