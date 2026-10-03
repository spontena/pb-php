# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.1.4] — 2026-10-03

### Security
- Require Guzzle `^7.15.2` and guzzlehttp/psr7 `^2.12.3` to exclude versions affected by the security advisories identified in the October 2026 dependency audit. Consumers must update their application dependencies to receive these fixes; an existing application lock file is not updated by this change alone.
- Replace transport exception messages with fixed descriptions and omit original exception chains so request query credentials cannot reach message logs. Standard Guzzle `ConnectException`, `RequestException`, and `TooManyRedirectsException` types and diagnostic accessors are retained; custom subclasses of these transport exceptions are normalized to the corresponding standard type.
- Omit response bodies from HTTP error messages. `ApiException` retains its HTTP status and explicit response-body accessors. Applications parsing exception messages must switch to the status/body accessors.
- Mark sensitive exception and request arguments with `SensitiveParameter` for PHP 8.2+. On PHP 8.1, set `zend.exception_ignore_args=On` before logging exception traces. Raw diagnostic accessors may still contain credentials and must not be logged without redaction.

### Changed
- Add dependency auditing, a weekly scheduled run, manual dispatch, and PHP 8.5 coverage to CI. Tests continue to use the unit suite without calling the live Pandorabots API.
- PHP `^8.1` and existing method signatures are retained.

## [2.1.3] — 2026-05-04

### Fixed
- `PBClient::upload()` now accepts bare-name local files for kinds whose URL has no filename component (`properties`, `pdefaults`). Previously, uploading a file literally named `properties` or `pdefaults` (with no extension) failed with `InvalidFileException: Unsupported file extension:` — even though `getBotFile()` returns those kinds without a filename component, making bare-name files the natural shape after a pull. This made `pull → push` not a clean roundtrip.
  - Files with an unknown extension or a bare basename that does not match any kind value still raise `InvalidFileException`.
  - A bare-name file matching a kind that requires a filename in the URL (`file`, `set`, `map`, `substitution`) is still rejected, since uploading it would be ambiguous.

## [2.1.2] — 2026-05-04

### Added
- `PBClient::upload()` gains an optional `?string $name = null` parameter. When provided, the upload uses that as the remote file name in the URL instead of deriving it from the local file's basename. Useful for alias-style uploads where the local source file lives at a path whose basename does not match the canonical name on the bot (e.g. uploading `variants/greet-debug.aiml` as `greet`).
  - The parameter is ignored for kinds whose URL has no filename component (`pdefaults`, `properties`).
  - Backwards-compatible: existing callers that pass two arguments behave exactly as before.

## [2.1.1] — 2026-05-03

### Fixed
- `PBClient::deleteBotFile()` no longer asserts a non-empty `$fname` for file kinds whose URL does not include a filename (`Pdefaults`, `Properties`). Passing an empty string for those kinds now works correctly — the `$fname` argument is silently ignored when building the URL, as it always was. File kinds that do include a filename in the URL (`File`, `Set`, `Map`, `Substitution`) still require a non-empty `$fname`.

## [2.1.0] — 2026-05-03

### Added
- `PBClient::getBotFile(FileKind $kind, string $botname, ?string $name = null): string` — fetch a single file's raw body from a bot. Returns the response as a `string` (no JSON decoding) so callers can save AIML / set / map / substitution / pdefaults / properties content directly to disk.
  - For kinds with a filename in the URL (`File`, `Set`, `Map`, `Substitution`), `$name` is required.
  - For kinds without a filename (`Pdefaults`, `Properties`), `$name` must be omitted.
  - HTTP 4xx/5xx still raise `ApiException` and the URL query (containing `user_key`) is redacted.

### Notes
- Backwards-compatible release — only the new method is added; no existing signatures or behaviors changed.

## [2.0.0] — 2026-05-03

### Changed (breaking)
- Required PHP version bumped to `^8.1`.
- HTTP client bumped to Guzzle `^7.5` (was `4.x`).
- Autoloading switched from PSR-0 to PSR-4; namespace renamed `spontena\pbphp` → `Spontena\PbPhp`.
- `deleteBotFile()` now takes a `FileKind` enum instead of a string.
- HTTP 4xx/5xx responses now raise `Spontena\PbPhp\Exception\ApiException` instead of returning `{status: "error", ...}` as an `stdClass`.
- `getBotsList()` now returns `list<\stdClass>` (top-level JSON array).
- Default API host updated to `https://api.pandorabots.com` (the `aiaas.pandorabots.com` host is no longer reachable).

### Added
- `atalk()` — anonymous talk via `POST /talk?botkey=…` for botkey-based authentication.
- `FileKind` enum (`File`, `Map`, `Set`, `Substitution`, `Pdefaults`, `Properties`) with `hasFilenameInPath()` helper.
- Exception hierarchy: `PandorabotsException`, `ApiException`, `InvalidArgumentException`, `InvalidFileException`.
- PHP 8.1 type declarations and `readonly` properties throughout `PBClient`.
- PHPUnit 10 test suite with both unit (`MockHandler`-based) and integration (real API) suites.
- GitHub Actions CI matrix on PHP 8.1 / 8.2 / 8.3 / 8.4 with PHPStan level 6.

### Fixed
- `debug()` previously referenced an undefined `$clientname` (camelCase) variable while the parameter was named `$client_name`. The result was that the `client_name` field was never sent. v2 fixes the typo and adds a regression test.
- `botname` and filenames are now `rawurlencode()`'d to handle special characters such as spaces, slashes, and ampersands.
- HTTP error messages no longer leak `user_key` / `botkey` query parameters into exception messages or stack traces.

### Removed
- The legacy `spontena/pbphp/` PSR-0 directory.
- Plaintext echoing of the request URL in exception chains (the previous Guzzle exception was attached as `previous`, exposing credentials).
