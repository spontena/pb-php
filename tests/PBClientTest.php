<?php

declare(strict_types=1);

namespace Spontena\PbPhp\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Spontena\PbPhp\Exception\ApiException;
use Spontena\PbPhp\Exception\InvalidArgumentException;
use Spontena\PbPhp\Exception\InvalidFileException;
use Spontena\PbPhp\FileKind;
use Spontena\PbPhp\PBClient;

final class PBClientTest extends TestCase
{
    private const HOST = 'https://aiaas.pandorabots.com';
    private const APP_ID = 'app123';
    private const USER_KEY = 'key456';

    /** @var list<array{request: Request, options: array<string, mixed>}> */
    private array $history = [];
    private MockHandler $mock;
    private PBClient $client;

    protected function setUp(): void
    {
        $this->history = [];
        $this->mock = new MockHandler();
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));
        $http = new Client(['handler' => $stack]);

        $this->client = new PBClient(
            host: self::HOST,
            appId: self::APP_ID,
            userKey: self::USER_KEY,
            http: $http,
        );
    }

    private function queueOk(string $body = '{"status":"ok"}'): void
    {
        $this->mock->append(new Response(200, [], $body));
    }

    private function lastRequest(): Request
    {
        $this->assertNotEmpty($this->history, 'No HTTP request was made.');
        $entry = $this->history[count($this->history) - 1];
        return $entry['request'];
    }

    public function testGetBotsListBuildsUrlAndUserKeyQuery(): void
    {
        $this->queueOk('[{"botname":"a","language":"en"},{"botname":"b","language":"en"}]');

        $result = $this->client->getBotsList();

        $req = $this->lastRequest();
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('/bot/app123', $req->getUri()->getPath());
        $this->assertSame('user_key=key456', $req->getUri()->getQuery());
        $this->assertSame('aiaas.pandorabots.com', $req->getUri()->getHost());
        $this->assertCount(2, $result);
        $this->assertSame('a', $result[0]->botname);
    }

    public function testCreatePutsBot(): void
    {
        $this->queueOk();
        $this->client->create('mybot');
        $req = $this->lastRequest();
        $this->assertSame('PUT', $req->getMethod());
        $this->assertSame('/bot/app123/mybot', $req->getUri()->getPath());
    }

    public function testDeleteBot(): void
    {
        $this->queueOk();
        $this->client->delete('mybot');
        $req = $this->lastRequest();
        $this->assertSame('DELETE', $req->getMethod());
        $this->assertSame('/bot/app123/mybot', $req->getUri()->getPath());
    }

    public function testGetBotFiles(): void
    {
        $this->queueOk('{"status":"ok","files":[]}');
        $this->client->getBotFiles('mybot');
        $req = $this->lastRequest();
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('/bot/app123/mybot', $req->getUri()->getPath());
    }

    public function testCompileUsesGetVerify(): void
    {
        $this->queueOk();
        $this->client->compile('mybot');
        $req = $this->lastRequest();
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('/bot/app123/mybot/verify', $req->getUri()->getPath());
    }

    public function testBotnameIsRawUrlEncoded(): void
    {
        $this->queueOk();
        $this->client->create('my bot/with&special');
        $req = $this->lastRequest();
        $this->assertSame('/bot/app123/my%20bot%2Fwith%26special', $req->getUri()->getPath());
    }

    public function testTalkSendsFormParamsAndUserKeyQuery(): void
    {
        $this->queueOk('{"status":"ok","responses":["hi"]}');

        $this->client->talk('hello', 'mybot', clientName: 'alice', sessionId: 'sess1');

        $req = $this->lastRequest();
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('/talk/app123/mybot', $req->getUri()->getPath());
        $this->assertSame('user_key=key456', $req->getUri()->getQuery());
        parse_str((string) $req->getBody(), $body);
        $this->assertSame('hello', $body['input']);
        $this->assertSame('alice', $body['client_name']);
        $this->assertSame('sess1', $body['sessionid']);
        $this->assertSame('true', $body['recent']);
    }

    public function testTalkRejectsEmptyInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->client->talk('', 'mybot');
    }

    public function testTalkRejectsEmptyBotname(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->client->talk('hi', '');
    }

    public function testDebugIncludesClientNameRegression(): void
    {
        // Regression: v1 had a bug where $clientname (vs $client_name) was undefined,
        // so client_name was never sent in debug() requests.
        $this->queueOk();

        $this->client->debug('hello', 'mybot', clientName: 'alice');

        $req = $this->lastRequest();
        parse_str((string) $req->getBody(), $body);
        $this->assertSame('alice', $body['client_name'], 'client_name must be sent in debug body');
        $this->assertSame('true', $body['trace']);
    }

    public function testDebugFlagsAreOptIn(): void
    {
        $this->queueOk();

        $this->client->debug('hi', 'mybot', reset: true, extra: true, reload: true, trace: false);

        $req = $this->lastRequest();
        parse_str((string) $req->getBody(), $body);
        $this->assertSame('true', $body['reset']);
        $this->assertSame('true', $body['extra']);
        $this->assertSame('true', $body['reload']);
        $this->assertArrayNotHasKey('trace', $body);
    }

    public function testUploadAimlGoesToFilePath(): void
    {
        $this->queueOk();
        $path = __DIR__ . '/Fixtures/sample.aiml';

        $this->client->upload($path, 'mybot');

        $req = $this->lastRequest();
        $this->assertSame('PUT', $req->getMethod());
        $this->assertSame('/bot/app123/mybot/file/sample', $req->getUri()->getPath());
        $this->assertSame('text/plain', $req->getHeaderLine('Content-Type'));
    }

    public function testUploadPropertiesGoesToKindOnlyPath(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pbphp');
        rename($tmp, $tmp . '.properties');
        $tmp .= '.properties';
        file_put_contents($tmp, "key=value\n");

        try {
            $this->queueOk();
            $this->client->upload($tmp, 'mybot');
            $req = $this->lastRequest();
            $this->assertSame('/bot/app123/mybot/properties', $req->getUri()->getPath());
        } finally {
            @unlink($tmp);
        }
    }

    public function testUploadRejectsUnknownExtension(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pbphp') . '.bogus';
        file_put_contents($tmp, 'x');

        try {
            $this->expectException(InvalidFileException::class);
            $this->client->upload($tmp, 'mybot');
        } finally {
            @unlink($tmp);
        }
    }

    public function testUploadRejectsMissingFile(): void
    {
        $this->expectException(InvalidFileException::class);
        $this->client->upload('/no/such/path.aiml', 'mybot');
    }

    public function testDeleteBotFileFileKind(): void
    {
        $this->queueOk();
        $this->client->deleteBotFile('greetings', FileKind::File, 'mybot');
        $this->assertSame('/bot/app123/mybot/file/greetings', $this->lastRequest()->getUri()->getPath());
    }

    public function testDeleteBotFileMapKind(): void
    {
        $this->queueOk();
        $this->client->deleteBotFile('colors', FileKind::Map, 'mybot');
        $this->assertSame('/bot/app123/mybot/map/colors', $this->lastRequest()->getUri()->getPath());
    }

    public function testDeleteBotFilePropertiesKindOmitsFilename(): void
    {
        $this->queueOk();
        $this->client->deleteBotFile('ignored', FileKind::Properties, 'mybot');
        $this->assertSame('/bot/app123/mybot/properties', $this->lastRequest()->getUri()->getPath());
    }

    public function testApiExceptionOn404(): void
    {
        $this->mock->append(new Response(404, [], '{"status":"error","message":"not found"}'));

        try {
            $this->client->getBotFiles('missing');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertStringContainsString('not found', $e->getResponseBody());
            $this->assertSame('error', $e->getDecodedBody()?->status);
        }
    }
}
