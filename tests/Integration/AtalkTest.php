<?php

declare(strict_types=1);

namespace Spontena\PbPhp\Tests\Integration;

/**
 * Exercises atalk() against the real API.
 *
 * atalk uses botkey-based authentication on POST /talk; the bot is identified
 * by the botkey alone (no path components), so only PB_BOT_KEY is required.
 * The bot referenced by the botkey must already exist, be compiled, and have
 * a valid bot key issued in the Pandorabots dashboard.
 */
final class AtalkTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $botKey = getenv('PB_BOT_KEY');
        if (!is_string($botKey) || $botKey === '') {
            $this->markTestSkipped('Set PB_BOT_KEY to run atalk integration tests.');
        }
    }

    public function testAtalkReturnsResponses(): void
    {
        $reply = $this->client->atalk('HELLO');

        $this->assertSame('ok', $reply->status, 'atalk should return status=ok');
        $this->assertObjectHasProperty('responses', $reply);
        $this->assertNotEmpty($reply->responses, 'atalk responses should be non-empty');
    }
}
