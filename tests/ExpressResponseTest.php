<?php

declare(strict_types=1);

namespace PivotPHP\Http\Tests;

use PHPUnit\Framework\TestCase;
use PivotPHP\Http\ExpressResponse;

class ExpressResponseTest extends TestCase
{
    public function testStatusAndHeaderAreFluentOnPsr7(): void
    {
        $response = (new ExpressResponse())->status(201)->header('X-App', 'PivotPHP');

        $this->assertSame(201, $response->psr7()->getStatusCode());
        $this->assertSame('PivotPHP', $response->psr7()->getHeaderLine('X-App'));
    }

    public function testJson(): void
    {
        $result = (new ExpressResponse())->json(['ok' => true]);

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('application/json; charset=utf-8', $result->getHeaderLine('Content-Type'));
        $this->assertSame('{"ok":true}', (string) $result->getBody());
    }

    public function testJsonHonoursFlags(): void
    {
        $result = (new ExpressResponse())->json(
            ['url' => 'https://example.com/path', 'emoji' => '🚀'],
            null,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $body = (string) $result->getBody();

        $this->assertStringContainsString('https://example.com/path', $body);
        $this->assertStringContainsString('🚀', $body);
        $this->assertStringNotContainsString('\\/', $body);
        $this->assertStringNotContainsString('\\u', $body);
    }

    public function testStatusAccumulatesIntoJson(): void
    {
        $result = (new ExpressResponse())->status(404)->json(['error' => 'nope']);

        $this->assertSame(404, $result->getStatusCode());
    }

    public function testTerminalUpdatesWrappedResponseWithoutReturn(): void
    {
        $response = (new ExpressResponse())->status(201);
        $response->json(['a' => 1]); // return intentionally ignored

        $this->assertSame(201, $response->psr7()->getStatusCode());
        $this->assertSame('{"a":1}', (string) $response->psr7()->getBody());
    }

    public function testText(): void
    {
        $result = (new ExpressResponse())->text('hello');

        $this->assertSame('text/plain; charset=utf-8', $result->getHeaderLine('Content-Type'));
        $this->assertSame('hello', (string) $result->getBody());
    }

    public function testHtml(): void
    {
        $result = (new ExpressResponse())->html('<h1>hi</h1>');

        $this->assertSame('text/html; charset=utf-8', $result->getHeaderLine('Content-Type'));
        $this->assertSame('<h1>hi</h1>', (string) $result->getBody());
    }

    public function testRedirect(): void
    {
        $result = (new ExpressResponse())->redirect('/login');

        $this->assertSame(302, $result->getStatusCode());
        $this->assertSame('/login', $result->getHeaderLine('Location'));
    }

    public function testRedirectKeepsLegitimateUrlCharacters(): void
    {
        $result = (new ExpressResponse())->redirect('/busca?q=a,b;c');

        $this->assertSame('/busca?q=a,b;c', $result->getHeaderLine('Location'));
    }

    public function testNoContent(): void
    {
        $this->assertSame(204, (new ExpressResponse())->noContent()->getStatusCode());
    }

    public function testSendUsesJsonForArrays(): void
    {
        $result = (new ExpressResponse())->send(['a' => 1]);

        $this->assertSame('application/json; charset=utf-8', $result->getHeaderLine('Content-Type'));
        $this->assertSame('{"a":1}', (string) $result->getBody());
    }

    public function testSendUsesTextForScalars(): void
    {
        $result = (new ExpressResponse())->send('hi');

        $this->assertSame('text/plain; charset=utf-8', $result->getHeaderLine('Content-Type'));
        $this->assertSame('hi', (string) $result->getBody());
    }

    public function testError(): void
    {
        $result = (new ExpressResponse())->error(500, 'boom');

        $this->assertSame(500, $result->getStatusCode());
        $this->assertSame('{"error":"boom"}', (string) $result->getBody());
    }

    public function testCookie(): void
    {
        $result = (new ExpressResponse())->cookie('session', 'abc', ['path' => '/', 'httpOnly' => true]);

        $this->assertSame('session=abc; Path=/; HttpOnly', $result->psr7()->getHeaderLine('Set-Cookie'));
    }

    public function testCookieRejectsInjectionInPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ExpressResponse())->cookie('session', 'abc', ['path' => '/; Domain=evil.com']);
    }

    public function testCookieRejectsControlCharactersInDomain(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ExpressResponse())->cookie('session', 'abc', ['domain' => "evil.com\r\nX-Evil: 1"]);
    }

    public function testCookieExpires(): void
    {
        $expires = new \DateTimeImmutable('2027-01-01 00:00:00', new \DateTimeZone('UTC'));

        $header = (new ExpressResponse())
            ->cookie('session', 'abc', ['expires' => $expires])
            ->psr7()
            ->getHeaderLine('Set-Cookie');

        $expected = 'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $expires->getTimestamp());
        $this->assertStringContainsString($expected, $header);
    }

    public function testCookieSameSiteNoneForcesSecure(): void
    {
        $header = (new ExpressResponse())
            ->cookie('session', 'abc', ['sameSite' => 'None'])
            ->psr7()
            ->getHeaderLine('Set-Cookie');

        $this->assertStringContainsString('Secure', $header);
        $this->assertStringContainsString('SameSite=None', $header);
    }

    public function testCookieRejectsInvalidSameSite(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ExpressResponse())->cookie('session', 'abc', ['sameSite' => 'Wat']);
    }
}
