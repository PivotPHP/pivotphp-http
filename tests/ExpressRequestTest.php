<?php

declare(strict_types=1);

namespace PivotPHP\Http\Tests;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use PivotPHP\Http\BodyParser;
use PivotPHP\Http\Exception\MalformedJsonException;
use PivotPHP\Http\ExpressRequest;

class ExpressRequestTest extends TestCase
{
    private function request(): ExpressRequest
    {
        $psr7 = new ServerRequest(
            'POST',
            '/users/42',
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
            '{"id":"42"}',
            '1.1',
            ['REMOTE_ADDR' => '1.2.3.4', 'HTTPS' => 'on']
        );

        $psr7 = $psr7
            ->withQueryParams(['page' => '2'])
            ->withParsedBody(['name' => 'pivot'])
            ->withCookieParams(['session' => 'abc'])
            ->withAttribute(ExpressRequest::ROUTE_PARAMS_ATTRIBUTE, ['id' => '42', 'slug' => 'hello-world']);

        return new ExpressRequest($psr7);
    }

    public function testMethodAndPath(): void
    {
        $request = $this->request();

        $this->assertSame('POST', $request->method());
        $this->assertSame('/users/42', $request->path());
    }

    public function testParamsReturnsAllRouteParameters(): void
    {
        $this->assertSame(
            ['id' => '42', 'slug' => 'hello-world'],
            $this->request()->params()
        );
    }

    public function testParamReadsRouteParams(): void
    {
        $this->assertSame('42', $this->request()->param('id'));
    }

    public function testParamReturnsDefaultWhenMissing(): void
    {
        $this->assertSame('x', $this->request()->param('missing', 'x'));
        $this->assertNull($this->request()->param('missing'));
    }

    public function testQuery(): void
    {
        $this->assertSame('2', $this->request()->query('page'));
        $this->assertSame('d', $this->request()->query('missing', 'd'));
    }

    public function testInputPrefersParsedBodyThenQuery(): void
    {
        $request = $this->request();

        $this->assertSame('pivot', $request->input('name'));
        $this->assertSame('2', $request->input('page'));
        $this->assertSame('d', $request->input('missing', 'd'));
    }

    public function testInputReadsJsonBodyAfterParsing(): void
    {
        $psr7 = (new BodyParser())->parse(
            new ServerRequest('POST', '/', ['Content-Type' => 'application/json'], '{"name":"a"}')
        );

        $this->assertSame('a', (new ExpressRequest($psr7))->input('name'));
    }

    public function testJsonDecodesObjectAndList(): void
    {
        $this->assertSame(['id' => '42'], $this->request()->json());

        $list = new ServerRequest('POST', '/', ['Content-Type' => 'application/json'], '[1,2]');
        $this->assertSame([1, 2], (new ExpressRequest($list))->json());
    }

    public function testJsonReturnsEmptyArrayForEmptyBody(): void
    {
        $this->assertSame([], (new ExpressRequest(new ServerRequest('POST', '/')))->json());
    }

    public function testJsonThrowsOnInvalidJson(): void
    {
        $psr7 = new ServerRequest('POST', '/', ['Content-Type' => 'application/json'], 'not-json');

        $this->expectException(MalformedJsonException::class);

        (new ExpressRequest($psr7))->json();
    }

    public function testHeader(): void
    {
        $request = $this->request();

        $this->assertSame('application/json', $request->header('Content-Type'));
        $this->assertSame('d', $request->header('X-Missing', 'd'));
        $this->assertNull($request->header('X-Missing'));
    }

    public function testCookie(): void
    {
        $this->assertSame('abc', $this->request()->cookie('session'));
        $this->assertNull($this->request()->cookie('missing'));
    }

    public function testIp(): void
    {
        $this->assertSame('1.2.3.4', $this->request()->ip());
    }

    public function testFlags(): void
    {
        $request = $this->request();

        $this->assertTrue($request->isAjax());
        $this->assertTrue($request->isJson());
        $this->assertTrue($request->isSecure());
        $this->assertTrue($request->accepts('application/json'));
        $this->assertFalse($request->accepts('text/html'));
    }

    public function testAcceptsHonoursWildcards(): void
    {
        $html = new ServerRequest('GET', '/', ['Accept' => 'text/*']);

        $this->assertTrue((new ExpressRequest($html))->accepts('text/html'));
        $this->assertFalse((new ExpressRequest($html))->accepts('application/json'));
    }

    public function testAcceptsHonoursQualityValues(): void
    {
        $request = new ServerRequest('GET', '/', ['Accept' => 'application/json;q=0, text/html']);

        $this->assertFalse((new ExpressRequest($request))->accepts('application/json'));
        $this->assertTrue((new ExpressRequest($request))->accepts('text/html'));
    }

    public function testAcceptsReturnsTrueWithoutAcceptHeader(): void
    {
        $this->assertTrue((new ExpressRequest(new ServerRequest('GET', '/')))->accepts('application/json'));
    }
}
