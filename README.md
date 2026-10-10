# PivotPHP HTTP

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![PSR-7](https://img.shields.io/badge/PSR--7-compliant-brightgreen)](https://www.php-fig.org/psr/psr-7/)
[![PSR-17](https://img.shields.io/badge/PSR--17-compliant-brightgreen)](https://www.php-fig.org/psr/psr-17/)

HTTP layer for PivotPHP: a thin **Express.js-style facade** on top of
[PSR-7](https://www.php-fig.org/psr/psr-7/) messages. The underlying representation is
[nyholm/psr7](https://github.com/Nyholm/psr7); PivotPHP adds the ergonomic API that a
framework inspired by Express.js needs.

This package is **pure HTTP**: it depends only on `nyholm/psr7`, `nyholm/psr7-server` and the
PSR interfaces — no dependency on the PivotPHP kernel, container or router.

## Why a facade?

PSR-7 is excellent at *representing* HTTP messages but deliberately minimal — it has no
`param()` or `json()` helpers. PivotPHP keeps its Express.js syntax by wrapping the PSR-7
objects:

```php
$app->get('/api/users/:id', function ($req, $res) {
    return $res->json([
        'id'   => $req->param('id'),
        'name' => 'John Doe',
    ]);
});
```

## Features

- **`ExpressRequest`** — read facade over `ServerRequestInterface`.
- **`ExpressResponse`** — fluent facade over `ResponseInterface`.
- **`BodyParser`** / **`Middleware\BodyParsingMiddleware`** — parse JSON and form bodies
  (including `PUT`/`PATCH`/`DELETE`).
- **`Emitter\SapiEmitter`** — emit the response to the SAPI (status/headers once, streamed body,
  no body on `HEAD`).
- **`Factory\Psr17Factory`** / **`Factory\ServerRequestFactory`** — PSR-17 and
  `fromGlobals()`/`fromArrays()`.
- **Type safety**: strict typing, PHPStan level 8.

## Installation

```bash
composer require pivotphp/http
```

## Usage

### Request

The router stores the matched route parameters as the `route_params` **attribute** on the
PSR-7 request; `ExpressRequest` reads them.

```php
use PivotPHP\Http\ExpressRequest;

$id     = $req->param('id');            // route parameter
$all    = $req->params();              // all route parameters
$page   = $req->query('page', '1');    // query string
$name   = $req->input('name');         // parsed body, then JSON body, then query
$data   = $req->json();                // decoded JSON body (array)
$token  = $req->header('Authorization');
$secure = $req->isSecure();
$html   = $req->accepts('text/html');  // content negotiation (wildcards + q)
```

### Response

Setters are fluent; terminal methods also update the wrapped response, so `psr7()` reflects the
final state even without capturing the return value.

```php
use PivotPHP\Http\ExpressResponse;

return $res->status(201)->header('X-App', 'PivotPHP')->json($data);
// or:
return $res->redirect('/login');
return $res->error(404, 'not found');
```

### Body parsing

PHP only fills `$_POST` for `POST` requests, so JSON and form bodies on `PUT`/`PATCH`/`DELETE`
are otherwise lost. Add `BodyParsingMiddleware` to the pipeline — it decodes JSON
(`application/json`, `application/*+json`) and parses form bodies, populating `getParsedBody()`:

```php
use PivotPHP\Http\Middleware\BodyParsingMiddleware;

$app->use(new BodyParsingMiddleware());
```

A malformed JSON body throws `MalformedJsonException`, which implements
`HttpExceptionInterface` (`getStatusCode()` returns `400`) — catch it and return `400`.

Parsing stays **in the pipeline** on purpose: `ServerRequestFactory` only builds the request, so
the exception is raised where an error handler can turn it into a response.

### Emitting the response

```php
use PivotPHP\Http\Emitter\SapiEmitter;

(new SapiEmitter())->emit($response, $request->getMethod());
```

`SapiEmitter` sends the status line and headers once, streams the body in chunks, omits the body
for `HEAD` and body-less statuses (`1xx`, `204`, `304`), and sets `Content-Length` when known.

## API

### `PivotPHP\Http\ExpressRequest` (wraps `ServerRequestInterface`)

| Method | Description |
|---|---|
| `params(): array` | All matched route parameters. |
| `param(string $name, mixed $default = null)` | Route parameter. |
| `query(string $name, mixed $default = null)` | Query-string value. |
| `input(string $name, mixed $default = null)` | Parsed body, then JSON body, then query string. |
| `json(): array` | Decoded JSON body; throws `MalformedJsonException` on malformed input. |
| `header(string $name, ?string $default = null)` | Header line. |
| `cookie(string $name, ?string $default = null)` | Cookie value. |
| `file(string $name)` | Uploaded file. |
| `ip()`, `userAgent()`, `method()`, `path()` | Request metadata. |
| `isAjax()`, `isJson()`, `isSecure()`, `accepts(string $type)` | Checks. |
| `psr7(): ServerRequestInterface` | The underlying PSR-7 request. |

### `PivotPHP\Http\ExpressResponse` (wraps `ResponseInterface`)

Fluent setters return `$this`; terminal senders update the wrapped response and return it.

| Method | Kind | Description |
|---|---|---|
| `status(int $code)` | setter | Set the status code. |
| `header(string $name, string $value)` | setter | Set a header. |
| `cookie(string $name, string $value, array $options = [])` | setter | Append `Set-Cookie`. |
| `json(mixed $data, ?int $status = null)` | sender | JSON response. |
| `text(string $text, ?int $status = null)` | sender | Plain-text response. |
| `html(string $html, ?int $status = null)` | sender | HTML response. |
| `redirect(string $url, int $status = 302)` | sender | Redirect. |
| `noContent(int $status = 204)` | sender | Empty response. |
| `send(mixed $data, ?int $status = null)` | sender | JSON for arrays/objects, text otherwise. |
| `error(int $status, string $message = '')` | sender | `{"error": "..."}` JSON. |
| `psr7(): ResponseInterface` | — | The wrapped PSR-7 response. |

### `PivotPHP\Http\Factory\ServerRequestFactory`

```php
use PivotPHP\Http\ExpressRequest;
use PivotPHP\Http\Factory\ServerRequestFactory;

$request = ServerRequestFactory::fromGlobals();   // reads $_SERVER, $_GET, $_POST, $_FILES, ...
$request = ServerRequestFactory::fromArrays($server, $headers, $cookies, $get, $post, $files);
$req = new ExpressRequest($request);
```

The factory only builds the request; body parsing is done by `BodyParsingMiddleware`.

## Requirements

- PHP 8.1 or higher
- `nyholm/psr7` `^1.8`, `nyholm/psr7-server` `^1.1`
- PSR-7 / PSR-15 / PSR-17 interfaces

## Testing

```bash
composer test           # PHPUnit
composer phpstan        # Static analysis (level 8)
composer cs:check       # PSR-12 style check
composer quality:check  # phpstan + cs:check + test
```

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Community

- [GitHub Issues](https://github.com/PivotPHP/pivotphp-http/issues)

## Credits

Created by Caio Alberto Fernandes and the PivotPHP community.
