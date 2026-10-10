<?php

declare(strict_types=1);

namespace PivotPHP\Http\Tests\Emitter;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PivotPHP\Http\Emitter\SapiEmitter;

class SapiEmitterTest extends TestCase
{
    private function capture(callable $emit): string
    {
        ob_start();

        try {
            $emit();
        } finally {
            $output = ob_get_clean();
        }

        return is_string($output) ? $output : '';
    }

    public function testEmitsBodyForGet(): void
    {
        $output = $this->capture(
            fn () => (new SapiEmitter())->emit(new Response(200, [], 'hello'), 'GET')
        );

        $this->assertSame('hello', $output);
    }

    public function testSkipsBodyForHead(): void
    {
        $output = $this->capture(
            fn () => (new SapiEmitter())->emit(new Response(200, [], 'hello'), 'HEAD')
        );

        $this->assertSame('', $output);
    }

    public function testSkipsBodyForNoContentStatus(): void
    {
        $output = $this->capture(
            fn () => (new SapiEmitter())->emit(new Response(204, [], 'ignored'), 'GET')
        );

        $this->assertSame('', $output);
    }

    public function testSkipsBodyForNotModifiedStatus(): void
    {
        $output = $this->capture(
            fn () => (new SapiEmitter())->emit(new Response(304, [], 'ignored'), 'GET')
        );

        $this->assertSame('', $output);
    }

    public function testStreamsBodyFromCurrentPosition(): void
    {
        $output = $this->capture(
            fn () => (new SapiEmitter(2))->emit(new Response(200, [], 'chunked'), 'GET')
        );

        $this->assertSame('chunked', $output);
    }
}
