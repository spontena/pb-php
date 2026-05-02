<?php

declare(strict_types=1);

namespace Spontena\PbPhp\Tests\Integration;

/**
 * Verifies that getBotsList() really returns a top-level JSON array
 * (the shape v2 assumes). Creates one bot to guarantee the list is non-empty.
 */
final class BotsListTest extends IntegrationTestCase
{
    public function testReturnsArrayContainingCreatedBot(): void
    {
        $botname = $this->reserveBotname('list');
        $this->client->create($botname);

        $bots = $this->client->getBotsList();

        $this->assertIsArray($bots, 'getBotsList must return a list (top-level JSON array)');
        $this->assertNotEmpty($bots);

        foreach ($bots as $bot) {
            $this->assertInstanceOf(\stdClass::class, $bot);
            $this->assertObjectHasProperty('botname', $bot);
        }

        $names = array_map(static fn (\stdClass $b) => $b->botname, $bots);
        $this->assertContains($botname, $names, 'newly created bot should appear in the list');
    }
}
