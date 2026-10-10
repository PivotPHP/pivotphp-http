<?php

declare(strict_types=1);

namespace PivotPHP\Http\Tests\Factory;

use PHPUnit\Framework\TestCase;
use PivotPHP\Http\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Psr17FactoryTest extends TestCase
{
    public function testCreateServerRequest(): void
    {
        $request = (new Psr17Factory())
            ->createServerRequest('GET', '/users/42', ['REMOTE_ADDR' => '127.0.0.1']);

        $this->assertInstanceOf(ServerRequestInterface::class, $request);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('127.0.0.1', $request->getServerParams()['REMOTE_ADDR']);
    }

    public function testCreateResponse(): void
    {
        $response = (new Psr17Factory())->createResponse(204);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testCreateStream(): void
    {
        $stream = (new Psr17Factory())->createStream('hello');

        $this->assertSame('hello', (string) $stream);
    }
}
