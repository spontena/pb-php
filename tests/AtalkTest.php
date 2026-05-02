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
use Spontena\PbPhp\Exception\InvalidArgumentException;
use Spontena\PbPhp\PBClient;

final class AtalkTest extends TestCase
{
    /** @var list<array{request: Request, options: array<string, mixed>}> */
    private array $history = [];
    private MockHandler $mock;

    private function makeClient(?string $botKey): PBClient
    {
        $this->history = [];
        $this->mock = new MockHandler();
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));
        $http = new Client(['handler' => $stack]);

        return new PBClient(
            host: 'https://api.pandorabots.com',
            appId: 'app123',
            userKey: 'key456',
            botKey: $botKey,
            http: $http,
        );
    }

    public function testAtalkRequiresBotKey(): void
    {
        $client = $this->makeClient(null);
        $this->expectException(InvalidArgumentException::class);
        $client->atalk('hi');
    }

    public function testAtalkRejectsEmptyInput(): void
    {
        $client = $this->makeClient('botkey789');
        $this->expectException(InvalidArgumentException::class);
        $client->atalk('');
    }

    public function testAtalkPostsToTalkPathWithAllParamsInQuery(): void
    {
        $client = $this->makeClient('botkey789');
        $this->mock->append(new Response(200, [], '{"status":"ok","responses":["hi"]}'));

        $client->atalk('hello', clientName: 'alice', sessionId: 'sess1');

        $req = $this->history[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('/talk', $req->getUri()->getPath(), 'botkey-based atalk uses /talk path, no app_id/botname');
        $this->assertSame('', (string) $req->getBody(), 'no body — params live in query');

        parse_str($req->getUri()->getQuery(), $query);
        $this->assertSame('botkey789', $query['botkey']);
        $this->assertSame('hello', $query['input']);
        $this->assertSame('alice', $query['client_name']);
        $this->assertSame('sess1', $query['sessionid']);
        $this->assertSame('true', $query['recent']);
        $this->assertArrayNotHasKey('user_key', $query, 'atalk must not authenticate via user_key');
    }
}
