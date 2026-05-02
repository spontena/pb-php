# pb-php

Pandorabots API client for PHP.

> See the official Pandorabots API documentation: <https://www.pandorabots.com/docs/api-endpoints/>

## Requirements

- PHP 8.1 or newer
- ext-json
- [Guzzle](https://github.com/guzzle/guzzle) 7.5+

## Installation

```bash
composer require spontena/pb-php
```

## Quickstart

```php
use Spontena\PbPhp\PBClient;
use Spontena\PbPhp\FileKind;
use Spontena\PbPhp\Exception\ApiException;

$pb = new PBClient(
    host:    'https://api.pandorabots.com',
    appId:   getenv('PB_APP_ID'),
    userKey: getenv('PB_USER_KEY'),
);

try {
    $pb->create('mybot');
    $pb->upload(__DIR__ . '/aiml/greetings.aiml', 'mybot');
    $pb->compile('mybot');

    $reply = $pb->talk('hello', 'mybot');
    foreach ($reply->responses as $line) {
        echo $line, "\n";
    }
} catch (ApiException $e) {
    fprintf(STDERR, "Pandorabots API error %d: %s\n", $e->getStatusCode(), $e->getResponseBody());
}
```

A working end-to-end script lives in [`examples/quickstart.php`](examples/quickstart.php). Run it after setting `PB_APP_ID` and `PB_USER_KEY`:

```bash
PB_APP_ID=xxx PB_USER_KEY=yyy php examples/quickstart.php
```

## API reference

| Method | HTTP | Endpoint |
|---|---|---|
| `getBotsList()` | `GET` | `/bot/{appId}` |
| `create($botname)` | `PUT` | `/bot/{appId}/{botname}` |
| `delete($botname)` | `DELETE` | `/bot/{appId}/{botname}` |
| `getBotFiles($botname)` | `GET` | `/bot/{appId}/{botname}` |
| `upload($path, $botname)` | `PUT` | `/bot/{appId}/{botname}/{kind}[/{name}]` |
| `deleteBotFile($name, FileKind $kind, $botname)` | `DELETE` | `/bot/{appId}/{botname}/{kind}[/{name}]` |
| `compile($botname)` | `GET` | `/bot/{appId}/{botname}/verify` |
| `talk($input, $botname, ...)` | `POST` | `/talk/{appId}/{botname}` |
| `debug($input, $botname, ...)` | `POST` | `/talk/{appId}/{botname}` (with `trace` etc.) |
| `atalk($input, ...)` | `POST` | `/talk?botkey=...` (botkey auth, bot identified by key) |

Successful responses are returned as `stdClass` (the decoded JSON body). `getBotsList()` returns a `list<\stdClass>` because the API responds with a top-level JSON array. HTTP 4xx/5xx responses raise `Spontena\PbPhp\Exception\ApiException`; bad inputs raise `InvalidArgumentException` or `InvalidFileException`.

`upload()` infers the file kind from the extension:

| Extension | `FileKind` | URL form |
|---|---|---|
| `.aiml` | `File` | `/bot/.../file/{name}` |
| `.set` | `Set` | `/bot/.../set/{name}` |
| `.map` | `Map` | `/bot/.../map/{name}` |
| `.substitution` | `Substitution` | `/bot/.../substitution/{name}` |
| `.pdefaults` | `Pdefaults` | `/bot/.../pdefaults` |
| `.properties` | `Properties` | `/bot/.../properties` |

## Migration from v1

v2 is a breaking-change release. Notable differences:

- **Namespace** is now `Spontena\PbPhp` (was `spontena\pbphp`); autoloading switched from PSR-0 to PSR-4.
- Constructor accepts named arguments and an optional `botKey` for `atalk()`.
- HTTP errors throw `ApiException` instead of returning `{ status: "error", ... }`.
- `deleteBotFile()` now takes a `FileKind` enum instead of a string.
- `botname` and filenames are URL-encoded.
- `debug()`'s broken `client_name` parameter (the long-standing `$clientname` typo) is fixed.
- Requires PHP 8.1+ and Guzzle 7.5+.

## Testing

### Unit tests (no API access)

```bash
composer install
composer test          # PHPUnit unit suite (mocked HTTP, runs by default)
composer analyse       # PHPStan (level 6)
```

CI runs the unit suite on PHP 8.1 / 8.2 / 8.3 / 8.4 — see `.github/workflows/ci.yml`.

### Integration tests (real Pandorabots API)

The `tests/Integration/` suite hits the live API to validate that endpoint URLs, request formats, and response shapes still match. It is **not** run by default. Provide credentials via env vars and invoke the `integration` suite explicitly:

```bash
PB_APP_ID=xxx PB_USER_KEY=yyy vendor/bin/phpunit --testsuite integration
```

Test bots are created with names like `pbphpci<hint><random>` (lowercase alphanumeric only — Pandorabots rejects hyphens) and removed in `tearDown()` even if a test fails.

To exercise `atalk()`, additionally set `PB_BOT_KEY` for an existing, compiled bot whose bot key has been issued in the Pandorabots dashboard:

```bash
PB_BOT_KEY=zzz \
PB_APP_ID=xxx PB_USER_KEY=yyy \
vendor/bin/phpunit --testsuite integration
```

The bot is identified by the bot key alone — no botname is needed in the request path.

| Env var | Purpose |
|---|---|
| `PB_APP_ID` | Pandorabots app id (required) |
| `PB_USER_KEY` | Pandorabots user key (required) |
| `PB_HOST` | API host, defaults to `https://api.pandorabots.com` |
| `PB_BOT_KEY` | Bot key for `atalk()` integration test (optional) |

## License

MIT — see [`LICENSE.txt`](LICENSE.txt).
