<?php

declare(strict_types=1);

namespace Spontena\PbPhp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Spontena\PbPhp\Exception\ApiException;
use Spontena\PbPhp\PBClient;

abstract class IntegrationTestCase extends TestCase
{
    protected PBClient $client;

    /** @var list<string> */
    private array $createdBots = [];

    protected function setUp(): void
    {
        $appId = getenv('PB_APP_ID');
        $userKey = getenv('PB_USER_KEY');

        if (!is_string($appId) || $appId === '' || !is_string($userKey) || $userKey === '') {
            $this->markTestSkipped('Set PB_APP_ID and PB_USER_KEY to run integration tests.');
        }

        $host = getenv('PB_HOST');
        $botKey = getenv('PB_BOT_KEY');

        $this->client = new PBClient(
            host: is_string($host) && $host !== '' ? $host : 'https://api.pandorabots.com',
            appId: $appId,
            userKey: $userKey,
            botKey: is_string($botKey) && $botKey !== '' ? $botKey : null,
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->createdBots as $botname) {
            try {
                $this->client->delete($botname);
            } catch (ApiException) {
                // best-effort cleanup; if the bot was already deleted, ignore
            }
        }
        $this->createdBots = [];
    }

    /**
     * Generate a unique botname and register it for tearDown cleanup.
     * Pandorabots rejects names containing hyphens (HTTP 400 "Invalid botname"),
     * so we restrict to lowercase [a-z0-9].
     */
    protected function reserveBotname(string $hint = 'test'): string
    {
        $cleanHint = substr(preg_replace('/[^a-z0-9]/', '', strtolower($hint)) ?: 'test', 0, 8);
        $name = sprintf('pbphpci%s%s', $cleanHint, bin2hex(random_bytes(4)));
        $this->createdBots[] = $name;
        return $name;
    }

    /**
     * Mark a botname for cleanup that the test creates outside reserveBotname().
     */
    protected function trackBotname(string $botname): void
    {
        $this->createdBots[] = $botname;
    }

    protected static function fixturesPath(string $relative): string
    {
        return dirname(__DIR__) . '/Fixtures/' . $relative;
    }
}
