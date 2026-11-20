<?php

declare(strict_types=1);

namespace Spontena\PbPhp;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Spontena\PbPhp\Exception\ApiException;
use Spontena\PbPhp\Exception\InvalidArgumentException;
use Spontena\PbPhp\Exception\InvalidFileException;

final class PBClient
{
    /** Present only in Guzzle 8, whose exception constructors differ from Guzzle 7. */
    private const GUZZLE8_RESPONSE_EXCEPTION = 'GuzzleHttp\\Exception\\ResponseException';

    /**
     * Log-safe descriptions for rebuilt transfer exceptions, checked in order
     * against the built-in class. Classes named by string exist only in Guzzle 8.
     */
    private const TRANSPORT_DESCRIPTIONS = [
        'GuzzleHttp\\Exception\\ConnectTimeoutException' => 'Pandorabots API connection timed out',
        ConnectException::class => 'Pandorabots API connection failed',
        'GuzzleHttp\\Exception\\NetworkTimeoutException' => 'Pandorabots API request timed out',
        'GuzzleHttp\\Exception\\NetworkException' => 'Pandorabots API network request failed',
        TooManyRedirectsException::class => 'Pandorabots API redirect limit exceeded',
        'GuzzleHttp\\Exception\\ResponseTimeoutException' => 'Pandorabots API response timed out',
        'GuzzleHttp\\Exception\\ResponseTransferException' => 'Pandorabots API response transfer failed',
    ];

    private readonly ClientInterface $http;

    public function __construct(
        private readonly string $host,
        private readonly string $appId,
        #[\SensitiveParameter] private readonly string $userKey,
        #[\SensitiveParameter] private readonly ?string $botKey = null,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client([
            RequestOptions::CONNECT_TIMEOUT => 5,
            RequestOptions::TIMEOUT => 30,
        ]);
    }

    /**
     * @return list<\stdClass>
     */
    public function getBotsList(): array
    {
        $response = $this->send('GET', sprintf('/bot/%s', rawurlencode($this->appId)));
        $body = (string) $response->getBody();
        $decoded = json_decode($body);
        if (!is_array($decoded)) {
            throw new ApiException(
                'Expected JSON array from /bot endpoint.',
                $response->getStatusCode(),
                $body,
            );
        }
        /** @var list<\stdClass> $decoded */
        return $decoded;
    }

    public function create(string $botname): \stdClass
    {
        $this->assertNotEmpty($botname, 'botname');
        return $this->request('PUT', $this->botPath($botname));
    }

    public function delete(string $botname): \stdClass
    {
        $this->assertNotEmpty($botname, 'botname');
        return $this->request('DELETE', $this->botPath($botname));
    }

    public function getBotFiles(string $botname): \stdClass
    {
        $this->assertNotEmpty($botname, 'botname');
        return $this->request('GET', $this->botPath($botname));
    }

    public function getBotFile(FileKind $kind, string $botname, ?string $name = null): string
    {
        $this->assertNotEmpty($botname, 'botname');

        if ($kind->hasFilenameInPath()) {
            if ($name === null || $name === '') {
                throw new InvalidArgumentException(sprintf('name is required for kind %s', $kind->value));
            }
        } else {
            if ($name !== null) {
                throw new InvalidArgumentException(sprintf('name must not be supplied for kind %s', $kind->value));
            }
        }

        $response = $this->send('GET', $this->fileKindPath($botname, $kind, $name ?? ''));
        return (string) $response->getBody();
    }

    /**
     * Upload a file to a bot.
     *
     * @param string      $fname   Local path of the file to upload. Its extension
     *                             determines the remote `FileKind`.
     * @param string      $botname Bot to upload to.
     * @param string|null $name    Optional remote file name to upload as. When
     *                             null (default), derived from `$fname`'s basename.
     *                             Pass an explicit name when the local file's
     *                             basename does not match the canonical name —
     *                             e.g. uploading `variants/greet-debug.aiml` as
     *                             `greet`. Ignored for kinds whose URL has no
     *                             filename component (`pdefaults`, `properties`).
     */
    public function upload(string $fname, string $botname, ?string $name = null): \stdClass
    {
        $this->assertNotEmpty($botname, 'botname');

        if (!is_file($fname)) {
            throw new InvalidFileException(sprintf('No such file: %s', $fname));
        }

        $extension = pathinfo($fname, PATHINFO_EXTENSION);
        if ($extension !== '') {
            $kind = FileKind::fromExtension($extension);
        } else {
            // Bare-name files (e.g. `properties`, `pdefaults`) have no extension
            // but their basename equals the kind value. The API returns these
            // kinds without a filename component, so this is the natural shape
            // for a pulled file on disk and round-tripping must accept it.
            $candidate = FileKind::fromExtension(pathinfo($fname, PATHINFO_BASENAME));
            $kind = ($candidate !== null && !$candidate->hasFilenameInPath()) ? $candidate : null;
        }
        if ($kind === null) {
            throw new InvalidFileException(sprintf('Unsupported file extension: %s', $extension));
        }

        $effectiveName = $name ?? pathinfo($fname, PATHINFO_FILENAME);
        $url = $this->fileKindPath($botname, $kind, $effectiveName);

        return $this->request('PUT', $url, [
            RequestOptions::BODY => Utils::tryFopen($fname, 'r'),
            RequestOptions::HEADERS => ['Content-Type' => 'text/plain'],
        ]);
    }

    public function deleteBotFile(string $fname, FileKind $fkind, string $botname): \stdClass
    {
        $this->assertNotEmpty($botname, 'botname');
        if ($fkind->hasFilenameInPath()) {
            $this->assertNotEmpty($fname, 'fname');
        }

        return $this->request('DELETE', $this->fileKindPath($botname, $fkind, $fname));
    }

    public function compile(string $botname): \stdClass
    {
        $this->assertNotEmpty($botname, 'botname');
        return $this->request('GET', sprintf('%s/verify', $this->botPath($botname)));
    }

    public function talk(
        #[\SensitiveParameter] string $input,
        string $botname,
        string $clientName = '',
        string $sessionId = '',
        bool $recent = true,
    ): \stdClass {
        $this->assertNotEmpty($input, 'input');
        $this->assertNotEmpty($botname, 'botname');

        return $this->request(
            'POST',
            $this->talkPath($botname, '/talk'),
            [RequestOptions::FORM_PARAMS => $this->talkParams($input, $clientName, $sessionId, $recent)],
        );
    }

    public function debug(
        #[\SensitiveParameter] string $input,
        string $botname,
        string $clientName = '',
        string $sessionId = '',
        bool $reset = false,
        bool $extra = false,
        bool $trace = true,
        bool $reload = false,
        bool $recent = true,
    ): \stdClass {
        $this->assertNotEmpty($input, 'input');
        $this->assertNotEmpty($botname, 'botname');

        $params = $this->talkParams($input, $clientName, $sessionId, $recent);
        if ($reset) {
            $params['reset'] = 'true';
        }
        if ($extra) {
            $params['extra'] = 'true';
        }
        if ($trace) {
            $params['trace'] = 'true';
        }
        if ($reload) {
            $params['reload'] = 'true';
        }

        return $this->request(
            'POST',
            $this->talkPath($botname, '/talk'),
            [RequestOptions::FORM_PARAMS => $params],
        );
    }

    public function atalk(
        #[\SensitiveParameter] string $input,
        string $clientName = '',
        string $sessionId = '',
        bool $recent = true,
    ): \stdClass {
        $this->assertNotEmpty($input, 'input');

        if ($this->botKey === null || $this->botKey === '') {
            throw new InvalidArgumentException('botKey is required to call atalk(); pass it to the constructor.');
        }

        // Pandorabots botkey-based talk uses POST /talk with all params in
        // the query string (the bot is identified by botkey, not by path).
        $query = ['botkey' => $this->botKey, 'input' => $input];
        if ($clientName !== '') {
            $query['client_name'] = $clientName;
        }
        if ($sessionId !== '') {
            $query['sessionid'] = $sessionId;
        }
        if ($recent) {
            $query['recent'] = 'true';
        }

        return $this->request(
            'POST',
            '/talk',
            [RequestOptions::QUERY => $query],
            authenticated: false,
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $path, #[\SensitiveParameter] array $options = [], bool $authenticated = true): \stdClass
    {
        $response = $this->send($method, $path, $options, $authenticated);
        $body = (string) $response->getBody();
        $decoded = json_decode($body);

        if (!$decoded instanceof \stdClass) {
            throw new ApiException(
                'Pandorabots API returned a non-object JSON response.',
                $response->getStatusCode(),
                $body,
            );
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function send(string $method, string $path, #[\SensitiveParameter] array $options = [], bool $authenticated = true): ResponseInterface
    {
        $url = rtrim($this->host, '/') . $path;

        if ($authenticated) {
            $options[RequestOptions::QUERY] = array_merge(
                $options[RequestOptions::QUERY] ?? [],
                ['user_key' => $this->userKey],
            );
        }

        try {
            return $this->http->request($method, $url, $options);
        } catch (BadResponseException $e) {
            throw ApiException::fromGuzzle($e);
        } catch (TransferException $e) {
            throw $this->safeTransferException($e);
        }
    }

    /**
     * Rebuild a Guzzle transfer exception with a fixed message and no previous
     * exception, so the original (URL-bearing) message and exception chain
     * never reach logs. Retry diagnostics stay available explicitly: the
     * rebuilt exception keeps the nearest built-in Guzzle class, the request,
     * any response, and on Guzzle 7 the handler context.
     *
     * Guzzle 7 and 8 construct these classes differently, so the class is
     * instantiated dynamically.
     */
    private function safeTransferException(#[\SensitiveParameter] TransferException $e): TransferException
    {
        $request = method_exists($e, 'getRequest') ? $e->getRequest() : null;
        if (!$request instanceof RequestInterface) {
            // Guzzle 7's base TransferException carries no request; leave it as is.
            return $e;
        }

        $class = self::builtInTransferClass($e);
        $message = $this->transportMessage(self::transportDescription($class), self::curlErrno($e));
        $response = method_exists($e, 'getResponse') ? $e->getResponse() : null;

        if (!class_exists(self::GUZZLE8_RESPONSE_EXCEPTION)) {
            $context = method_exists($e, 'getHandlerContext') ? $e->getHandlerContext() : [];

            return is_a($class, ConnectException::class, true)
                ? new $class($message, $request, null, $context)
                : new $class($message, $request, $response, null, $context);
        }

        // Guzzle 8: response-aware classes require the response; the others
        // take the message and request only.
        return $response instanceof ResponseInterface
            ? new $class($message, $request, $response)
            : new $class($message, $request);
    }

    /**
     * Custom subclasses raised by an injected client may declare their own
     * constructors, so they are rebuilt as their nearest built-in Guzzle class.
     *
     * @return class-string<TransferException>
     */
    private static function builtInTransferClass(#[\SensitiveParameter] TransferException $e): string
    {
        foreach ([$e::class, ...array_values(class_parents($e))] as $class) {
            // Anonymous subclasses are named after their parent ("...\ConnectException@anonymous").
            if (
                str_starts_with($class, 'GuzzleHttp\\Exception\\')
                && is_a($class, TransferException::class, true)
                && !(new \ReflectionClass($class))->isAnonymous()
            ) {
                return $class;
            }
        }

        return TransferException::class;
    }

    private static function transportDescription(string $class): string
    {
        foreach (self::TRANSPORT_DESCRIPTIONS as $base => $description) {
            if (is_a($class, $base, true)) {
                return $description;
            }
        }

        return 'Pandorabots API request failed';
    }

    /**
     * Guzzle 7 reports the cURL error code in the handler context. Guzzle 8
     * removed that accessor, so the leading "cURL error N:" of its message is
     * read instead; only the digits are kept.
     */
    private static function curlErrno(#[\SensitiveParameter] TransferException $e): ?int
    {
        if (method_exists($e, 'getHandlerContext')) {
            $errno = $e->getHandlerContext()['errno'] ?? null;

            return is_int($errno) ? $errno : null;
        }

        return preg_match('/^cURL error (\d+):/', $e->getMessage(), $matches) === 1 ? (int) $matches[1] : null;
    }

    /**
     * Build a credential-free transport error message. Only the numeric cURL
     * error code is appended so timeouts, DNS failures, and TLS errors remain
     * distinguishable in logs without echoing the original (URL-bearing) text.
     */
    private function transportMessage(string $description, ?int $errno): string
    {
        if ($errno !== null && $errno > 0) {
            return sprintf('%s (cURL error %d).', $description, $errno);
        }

        return $description . '.';
    }

    private function botPath(string $botname): string
    {
        return sprintf('/bot/%s/%s', rawurlencode($this->appId), rawurlencode($botname));
    }

    private function fileKindPath(string $botname, FileKind $kind, string $filename): string
    {
        $base = sprintf('%s/%s', $this->botPath($botname), rawurlencode($kind->value));

        if (!$kind->hasFilenameInPath()) {
            return $base;
        }

        return sprintf('%s/%s', $base, rawurlencode($filename));
    }

    private function talkPath(string $botname, string $prefix): string
    {
        return sprintf('%s/%s/%s', $prefix, rawurlencode($this->appId), rawurlencode($botname));
    }

    /**
     * @return array<string, string>
     */
    private function talkParams(string $input, string $clientName, string $sessionId, bool $recent): array
    {
        $params = ['input' => $input];
        if ($clientName !== '') {
            $params['client_name'] = $clientName;
        }
        if ($sessionId !== '') {
            $params['sessionid'] = $sessionId;
        }
        if ($recent) {
            $params['recent'] = 'true';
        }
        return $params;
    }

    private function assertNotEmpty(string $value, string $name): void
    {
        if ($value === '') {
            throw new InvalidArgumentException(sprintf('%s must not be empty.', $name));
        }
    }
}
