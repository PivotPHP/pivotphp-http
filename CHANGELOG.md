# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-10

### Added

- **`PivotPHP\Http\ExpressRequest`** — Express.js-style read facade over
  `Psr\Http\Message\ServerRequestInterface`: `params()`, `param()`, `query()`, `input()`,
  `json()`, `header()`, `cookie()`, `file()`, `ip()`, `userAgent()`, `isAjax()`, `isJson()`,
  `isSecure()` and `accepts()` (honours `Accept` wildcards and `q` values).
- **`PivotPHP\Http\ExpressResponse`** — fluent Express.js-style facade over
  `Psr\Http\Message\ResponseInterface`: setters `status()`, `header()`, `cookie()` and senders
  `json()`, `text()`, `html()`, `redirect()`, `noContent()`, `send()`, `error()`.
- **`PivotPHP\Http\BodyParser`** — populates the request parsed body from JSON
  (`application/json` and `application/*+json`) and `application/x-www-form-urlencoded` bodies
  (including `PUT`/`PATCH`/`DELETE`).
- **`PivotPHP\Http\Middleware\BodyParsingMiddleware`** — PSR-15 middleware wrapping
  `BodyParser`; this is where body parsing happens, inside the pipeline.
- **`PivotPHP\Http\Emitter\SapiEmitter`** — emits a PSR-7 response to the SAPI: status and
  headers once, streams the body in chunks, omits the body for `HEAD` and body-less statuses
  (`1xx`, `204`, `304`), and sets `Content-Length` when known.
- **`PivotPHP\Http\Exception\HttpExceptionInterface`** — exposes `getStatusCode()`; thrown
  exceptions carry the HTTP status so a framework can map them generically.
- **`PivotPHP\Http\Factory\Psr17Factory`** — the package's PSR-17 entry point (delegates to
  nyholm/psr7).
- **`PivotPHP\Http\Factory\ServerRequestFactory`** — builds a PSR-7 server request from the PHP
  SAPI environment (`fromGlobals()`) or plain arrays (`fromArrays()`).

### Changed

- `ExpressResponse` terminal methods (`json()`, `text()`, ...) now also update the wrapped
  PSR-7 response, so `psr7()` reflects the final state even when the return value is ignored.
- `ServerRequestFactory` **only builds** the request; body parsing happens solely in
  `BodyParsingMiddleware`, so a malformed body throws inside the pipeline (map to `400`) rather
  than before any handler exists.
- `ExpressRequest::input()` reads the parsed body (populated by the middleware, or PHP for
  `POST` form bodies) then the query string.

### Fixed

- `ExpressResponse::redirect()` no longer strips `,`/`;` from the URL (legitimate characters);
  nyholm/psr7 already rejects CR/LF in header values.
- `ExpressResponse::cookie()` rejects `path`/`domain` values containing `;`, `,` or control
  characters (throwing `InvalidArgumentException`) instead of silently altering them.
- `ExpressRequest::json()` throws `MalformedJsonException` on a malformed non-empty body
  (instead of returning an empty array). A JSON scalar/`null` body yields an empty parsed body
  (not an error — the parsed body is a keyed structure).

### Notes

- The package depends only on `nyholm/psr7`, `nyholm/psr7-server` and the PSR interfaces — no
  dependency on the PivotPHP kernel, container or router.
