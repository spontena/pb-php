<?php

declare(strict_types=1);

namespace Spontena\PbPhp\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Spontena\PbPhp\Exception\ApiException;
use Spontena\PbPhp\PBClient;

final class ExceptionSecurityTest extends TestCase
{
    private const KEY = 'dummy-review-key+/&';
    private string $previousIgnoreArgs;

    protected function setUp(): void
    {
        // Exercise SensitiveParameter with trace arguments enabled on PHP 8.2+.
        // PHP 8.1 requires this production setting to avoid logging arguments.
        $this->previousIgnoreArgs = (string) ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', PHP_VERSION_ID >= 80200 ? '0' : '1');
    }

    protected function tearDown(): void
    {
        ini_set('zend.exception_ignore_args', $this->previousIgnoreArgs);
    }

    public static function transportFailures(): iterable
    {
        foreach ([false, true] as $atalk) {
            foreach ([ConnectException::class, RequestException::class, TooManyRedirectsException::class] as $type) {
                yield ($atalk ? 'botkey' : 'user_key') . '-' . $type => [$atalk, $type];
            }
        }
    }

    #[DataProvider('transportFailures')]
    public function testTransportMessagesAreSafeAndDiagnosticsArePreserved(bool $atalk, string $type): void
    {
        $sent = null;
        $response = new Response(302, [], 'dummy private response');
        $context = ['errno' => 28, 'error' => self::KEY];
        $handler = static function (RequestInterface $request) use ($type, $response, $context, &$sent) {
            $sent = $request;
            $message = 'Transport failure for ' . $request->getUri();
            $previous = new \RuntimeException('Nested failure ' . self::KEY);
            $failure = $type === ConnectException::class
                ? new ConnectException($message, $request, $previous, $context)
                : new $type($message, $request, $response, $previous, $context);

            return Create::rejectionFor($failure);
        };
        $client = $this->client(new Client(['handler' => $handler]));

        try {
            $atalk ? $client->atalk('dummy private input') : $client->getBotsList();
            self::fail('Expected a transport exception');
        } catch (ConnectException | RequestException $e) {
            self::assertSame($type, $e::class);
            self::assertNull($e->getPrevious());
            $this->assertSafeMessage($e);
            self::assertSame($sent, $e->getRequest());
            self::assertSame($context, $e->getHandlerContext());
            self::assertStringContainsString('(cURL error 28).', $e->getMessage());
            if ($e instanceof RequestException) {
                self::assertSame($response, $e->getResponse());
                self::assertSame(302, $e->getCode());
            } else {
                self::assertSame(0, $e->getCode());
            }
        }
    }

    public static function httpFailures(): iterable
    {
        foreach ([false, true] as $atalk) {
            foreach ([400, 500] as $status) {
                yield ($atalk ? 'botkey' : 'user_key') . '-' . $status => [$atalk, $status];
            }
        }
    }

    #[DataProvider('httpFailures')]
    public function testHttpMessagesOmitEchoedSecretsButRetainResponseAccess(bool $atalk, int $status): void
    {
        $body = json_encode(['message' => self::KEY, 'input' => 'dummy private input'], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response($status, [], $body)]);
        $client = $this->client(new Client(['handler' => HandlerStack::create($mock)]));

        try {
            $atalk ? $client->atalk('dummy private input') : $client->getBotsList();
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertNull($e->getPrevious());
            $this->assertSafeMessage($e);
            self::assertSame($status, $e->getStatusCode());
            self::assertSame($status, $e->getCode());
            self::assertStringContainsString('HTTP ' . $status, $e->getMessage());
            self::assertSame($body, $e->getResponseBody());
            self::assertSame(self::KEY, $e->getDecodedBody()?->message);
        }
    }

    private function client(Client $http): PBClient
    {
        return new PBClient('https://example.invalid', 'dummy-app', self::KEY, self::KEY, $http);
    }

    private function assertSafeMessage(\Throwable $e): void
    {
        // Check full trace arguments as well: stringified traces may truncate
        // strings and would otherwise hide a leak from this regression test.
        $trace = print_r($e->getTrace(), true);
        self::assertStringNotContainsString(self::KEY, $trace);
        self::assertStringNotContainsString(rawurlencode(self::KEY), $trace);
        self::assertStringNotContainsString('dummy private input', $trace);
        foreach ([$e->getMessage(), (string) $e] as $text) {
            self::assertStringNotContainsString(self::KEY, $text);
            self::assertStringNotContainsString(rawurlencode(self::KEY), $text);
            self::assertStringNotContainsString('user_key', $text);
            self::assertStringNotContainsString('botkey', $text);
            self::assertStringNotContainsString('dummy private input', $text);
            self::assertStringNotContainsString('dummy private response', $text);
        }
    }
}
