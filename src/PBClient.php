<?php

declare(strict_types=1);

namespace Spontena\PbPhp;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Spontena\PbPhp\Exception\ApiException;
use Spontena\PbPhp\Exception\InvalidArgumentException;
use Spontena\PbPhp\Exception\InvalidFileException;

final class PBClient
{
    private readonly ClientInterface $http;

    public function __construct(
        private readonly string $host,
        private readonly string $appId,
        private readonly string $userKey,
        private readonly ?string $botKey = null,
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
        $kind = FileKind::fromExtension($extension);
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
        string $input,
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
        string $input,
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
        string $input,
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
    private function request(string $method, string $path, array $options = [], bool $authenticated = true): \stdClass
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
    private function send(string $method, string $path, array $options = [], bool $authenticated = true): ResponseInterface
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
        }
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
