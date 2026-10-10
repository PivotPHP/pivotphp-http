# CLAUDE.md - PivotPHP HTTP

Este arquivo orienta o trabalho no pacote **pivotphp/http**.

## Visão geral

**pivotphp/http** é a camada HTTP do PivotPHP: uma **fachada fina no estilo Express.js** sobre
mensagens [PSR-7](https://www.php-fig.org/psr/psr-7/). A representação HTTP é delegada ao
[nyholm/psr7](https://github.com/Nyholm/psr7); o pacote adiciona a API ergonômica que um
framework inspirado no Express.js precisa (`$req->param()`, `$res->json()`, etc.).

O pacote é **HTTP puro**: depende de `nyholm/psr7` (mensagens) e `nyholm/psr7-server` (criação a
partir do SAPI) e das interfaces PSR — sem kernel, container ou roteador. Isso evita ciclos de
dependência com o `pivotphp-core` e mantém a camada reutilizável.

## Estrutura

```
pivotphp-http/
├── src/
│   ├── ExpressRequest.php            # fachada de leitura sobre ServerRequestInterface
│   ├── ExpressResponse.php           # fachada fluente sobre ResponseInterface
│   ├── BodyParser.php                # parseia corpo (JSON + form) -> parsedBody
│   ├── Exception/
│   │   ├── HttpExceptionInterface.php
│   │   └── MalformedJsonException.php
│   ├── Emitter/
│   │   └── SapiEmitter.php           # emite a resposta para o SAPI
│   ├── Middleware/
│   │   └── BodyParsingMiddleware.php # PSR-15 sobre BodyParser
│   └── Factory/
│       ├── Psr17Factory.php          # PSR-17 (envolve o nyholm/psr7)
│       └── ServerRequestFactory.php  # fromGlobals/fromArrays (+ BodyParser)
└── tests/
    ├── ExpressRequestTest.php
    ├── ExpressResponseTest.php
    ├── Emitter/SapiEmitterTest.php
    ├── Middleware/BodyParsingMiddlewareTest.php
    └── Factory/{Psr17FactoryTest,ServerRequestFactoryTest}.php
```

## Comandos essenciais

```bash
composer test           # PHPUnit
composer phpstan        # PHPStan nível 9 (phpstan analyse src --level=9)
composer cs:check       # PSR-12 (phpcs --standard=PSR12 src tests)
composer cs:fix         # Auto-correção
composer quality:check  # phpstan + cs:check + test
```

## Decisões de design

- **PSR-7 por baixo, Express por cima**: usa `nyholm/psr7` como base e uma camada de fachada
  (`ExpressRequest`/`ExpressResponse`) para a sintaxe Express.js. Não herdamos do nyholm — ele
  marca as classes como `@final`; usamos composição (delegação).
- **Parâmetros de rota** chegam como **atributo** `ExpressRequest::ROUTE_PARAMS_ATTRIBUTE`
  (`route_params`) no `ServerRequest`; o roteador/consumidor popula esse atributo e a fachada o lê.
- **`ExpressRequest` é somente leitura**: expõe acessores; o `ServerRequest` PSR-7 permanece
  imutável. `psr7()` dá acesso ao objeto PSR-7 subjacente.
- **`ExpressResponse`**: os *setters* (`status()`, `header()`, `cookie()`) são fluentes e mutam a
  mensagem PSR-7 interna, retornando `$this`; os *terminais* (`json()`, `text()`, `html()`,
  `redirect()`, `send()`, `noContent()`, `error()`) **também atualizam** a mensagem interna (para
  que `psr7()` reflita o estado final mesmo sem capturar o retorno) e retornam a
  `ResponseInterface` final. Quando o status não é passado ao terminal, o acumulado via
  `status()` é mantido.
- **Body parsing**: `BodyParser` preenche `getParsedBody()` a partir do corpo cru — decodifica
  `application/json` e `application/*+json` (qualquer método) e faz `parse_str` de
  `application/x-www-form-urlencoded` em `PUT`/`PATCH`/`DELETE` (o PHP só preenche `$_POST` em
  `POST`). **O parsing acontece só na pipeline**, via `Middleware\BodyParsingMiddleware`;
  `Factory\ServerRequestFactory` **apenas monta** o request (não parseia), para que JSON
  malformado estoure dentro da pipeline (onde vira `400`) e não antes de qualquer handler. JSON
  malformado lança `Exception\MalformedJsonException`, que implementa
  `Exception\HttpExceptionInterface` (`getStatusCode(): int` → 400). Um corpo JSON que decodifica
  para escalar/null rende parsedBody vazio (não é erro).
- **Emissão**: `Emitter\SapiEmitter::emit($response, $method)` envia status+cabeçalhos uma vez,
  transmite o corpo em chunks, **não** envia corpo em `HEAD` nem em status sem corpo (`1xx`,
  `204`, `304`), e define `Content-Length` quando o tamanho é conhecido.
- **`cookie()`**: `path`/`domain` com `;`, `,` ou caracteres de controle lançam
  `InvalidArgumentException` (não são silenciosamente alterados). Suporta
  `expires`/`maxAge`/`sameSite`, e `SameSite=None` força `Secure`.
- **`redirect()`**: não sanitiza a URL (`,`/`;` são legítimos); CR/LF é rejeitado pelo nyholm.
- **`accepts()`**: respeita curingas (`text/*`, `*/*`) e `q`; `q=0` significa "não aceitável"
  (o match mais específico vence).
- **Sem dependência do kernel**: nada de DI, eventos ou configuração aqui.

## Convenções

- `declare(strict_types=1)`, PSR-12, PHPStan nível 9 (tolerância zero).
- Value types em `array<...>` são recomendados (documentação), mas o nível 8 não os exige.
- **Uma ferramenta de estilo**: PHPCS (`phpcs`/`phpcbf`, standard PSR-12). Não adicionar
  php-cs-fixer.

## Versionamento

- **Versão atual**: 1.0.0
- **PHP**: 8.1+
- **Licença**: MIT

## Suporte

- **GitHub**: https://github.com/PivotPHP/pivotphp-http
- **Packagist**: https://packagist.org/packages/pivotphp/http
