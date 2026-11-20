<?php

declare(strict_types=1);

namespace Spontena\PbPhp\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Spontena\PbPhp\Exception\ApiException;
use Spontena\PbPhp\PBClient;

final class ExceptionSecurityTest extends TestCase
{
    private const KEY = 'dummy-review-key+/&';
    private const GUZZLE8_RESPONSE_EXCEPTION = 'GuzzleHttp\\Exception\\ResponseException';
    private string $previousIgnoreArgs;

    protected function setUp(): void
    {
        // Exercise SensitiveParameter with trace arguments enabled.
        $this->previousIgnoreArgs = (string) ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
    }

    protected function tearDown(): void
    {
        ini_set('zend.exception_ignore_args', $this->previousIgnoreArgs);
    }

    public static function transportFailures(): iterable
    {
        $types = self::isGuzzle8()
            ? [
                ConnectException::class,
                'GuzzleHttp\\Exception\\ConnectTimeoutException',
                'GuzzleHttp\\Exception\\NetworkException',
                'GuzzleHttp\\Exception\\NetworkTimeoutException',
                RequestException::class,
                TooManyRedirectsException::class,
                'GuzzleHttp\\Exception\\ResponseTransferException',
                'GuzzleHttp\\Exception\\ResponseTimeoutException',
                'GuzzleHttp\\Exception\\HandlerClosedException',
            ]
            : [ConnectException::class, RequestException::class, TooManyRedirectsException::class];

        foreach ([false, true] as $atalk) {
            foreach ($types as $type) {
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
            $message = 'cURL error 28: Operation timed out for ' . $request->getUri();
            $previous = new \RuntimeException('Nested failure ' . self::KEY);

            return Create::rejectionFor(self::transferFailure($type, $message, $request, $response, $previous, $context));
        };
        $client = $this->client(new Client(['handler' => $handler]));
        $expectsResponse = self::isGuzzle8()
            ? is_a($type, self::GUZZLE8_RESPONSE_EXCEPTION, true)
            : $type !== ConnectException::class;

        try {
            $atalk ? $client->atalk('dummy private input') : $client->getBotsList();
            self::fail('Expected a transport exception');
        } catch (TransferException $e) {
            self::assertSame($type, $e::class);
            self::assertNull($e->getPrevious());
            $this->assertSafeMessage($e);
            self::assertSame($sent, $e->getRequest());
            self::assertStringContainsString('(cURL error 28).', $e->getMessage());
            if (self::isGuzzle8()) {
                self::assertFalse(method_exists($e, 'getHandlerContext'));
            } else {
                self::assertSame($context, $e->getHandlerContext());
            }
            if ($expectsResponse) {
                self::assertSame($response, $e->getResponse());
                self::assertSame(302, $e->getCode());
            } else {
                self::assertSame(0, $e->getCode());
            }
        }
    }

    public function testCustomTransportSubclassesAreRebuiltAsTheBuiltInClass(): void
    {
        $handler = static function (RequestInterface $request) {
            $message = 'cURL error 7: Failed to connect for ' . $request->getUri();

            return Create::rejectionFor(new class ($message, $request) extends ConnectException {
            });
        };
        $client = $this->client(new Client(['handler' => $handler]));

        try {
            $client->getBotsList();
            self::fail('Expected ConnectException');
        } catch (ConnectException $e) {
            self::assertSame(ConnectException::class, $e::class);
            $this->assertSafeMessage($e);
            // Guzzle 8 carries the cURL code only in the message; this custom
            // Guzzle 7 exception has no handler context to read it from.
            self::assertSame(
                self::isGuzzle8() ? 'Pandorabots API connection failed (cURL error 7).' : 'Pandorabots API connection failed.',
                $e->getMessage(),
            );
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

    private static function isGuzzle8(): bool
    {
        return class_exists(self::GUZZLE8_RESPONSE_EXCEPTION);
    }

    /**
     * Build a transfer exception the way the installed Guzzle major expects.
     *
     * @param array<string, mixed> $context
     */
    private static function transferFailure(
        string $type,
        string $message,
        RequestInterface $request,
        ResponseInterface $response,
        \Throwable $previous,
        array $context,
    ): TransferException {
        if (!self::isGuzzle8()) {
            return $type === ConnectException::class
                ? new ConnectException($message, $request, $previous, $context)
                : new $type($message, $request, $response, $previous, $context);
        }
        if (is_a($type, self::GUZZLE8_RESPONSE_EXCEPTION, true)) {
            return new $type($message, $request, $response, $previous);
        }
        if (is_a($type, NetworkExceptionInterface::class, true)) {
            return new $type($message, $request, $previous);
        }

        return new $type($message, $request, 0, $previous);
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
