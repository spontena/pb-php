<?php

declare(strict_types=1);

namespace Spontena\PbPhp\Tests\Integration;

use Spontena\PbPhp\FileKind;

/**
 * End-to-end test against the real Pandorabots API.
 * Exercises the full bot lifecycle: create → upload → compile → list files →
 * fetch file content → talk → debug → deleteBotFile → delete.
 *
 * Run with:
 *   PB_APP_ID=xxx PB_USER_KEY=yyy vendor/bin/phpunit --testsuite integration
 */
final class BotLifecycleTest extends IntegrationTestCase
{
    public function testFullLifecycle(): void
    {
        $botname = $this->reserveBotname('lifecycle');

        // 1. create
        $created = $this->client->create($botname);
        $this->assertSame('ok', $created->status, 'create should return status=ok');

        // 2. upload AIML
        $uploaded = $this->client->upload(self::fixturesPath('sample.aiml'), $botname);
        $this->assertSame('ok', $uploaded->status, 'upload should return status=ok');

        // 3. compile (verify)
        $compiled = $this->client->compile($botname);
        $this->assertSame('ok', $compiled->status, 'compile should return status=ok');

        // 4. list files — uploaded sample should appear (Pandorabots may report
        // it with or without the extension; accept both forms).
        $files = $this->client->getBotFiles($botname);
        $this->assertObjectHasProperty('files', $files);
        $names = array_map(static fn (\stdClass $f) => $f->name, $files->files);
        $this->assertNotEmpty(
            array_intersect(['sample', 'sample.aiml'], $names),
            sprintf('uploaded sample should be listed in files; got: %s', implode(',', $names)),
        );

        // 4b. fetch single file content — pb-php v2.1 getBotFile()
        $content = $this->client->getBotFile(FileKind::File, $botname, 'sample');
        $this->assertNotEmpty($content, 'getBotFile should return non-empty body');
        $this->assertStringContainsString('HELLO', $content, 'fetched AIML should contain the original pattern');
        $this->assertStringContainsString('Hello, world.', $content, 'fetched AIML should contain the original template');

        // 5. talk — sample.aiml answers HELLO with "Hello, world."
        $reply = $this->client->talk('HELLO', $botname);
        $this->assertSame('ok', $reply->status, 'talk should return status=ok');
        $this->assertNotEmpty($reply->responses, 'talk responses should be non-empty');

        // 6. debug — same input but with trace; client_name regression check
        $debug = $this->client->debug('HELLO', $botname, clientName: 'phpunit');
        $this->assertSame('ok', $debug->status, 'debug should return status=ok');

        // 7. deleteBotFile — File kind with explicit name
        $deletedFile = $this->client->deleteBotFile('sample', FileKind::File, $botname);
        $this->assertSame('ok', $deletedFile->status, 'deleteBotFile should return status=ok');

        // 7b. deleteBotFile with empty fname for Properties kind (v2.1.1 fix).
        // Upload a minimal properties file first so there is something to delete.
        // Pandorabots expects properties in JSON [[key, value], ...] form;
        // plain key=value text is rejected with HTTP 400 "request is malformed".
        $propsFile = tempnam(sys_get_temp_dir(), 'pbphp_') . '.properties';
        file_put_contents($propsFile, (string) json_encode([
            ['botname', 'LifecycleBot'],
            ['author', 'phpunit'],
        ]));
        $this->client->upload($propsFile, $botname);
        unlink($propsFile);

        $deletedProps = $this->client->deleteBotFile('', FileKind::Properties, $botname);
        $this->assertSame('ok', $deletedProps->status, 'deleteBotFile with empty fname should work for Properties');

        // 8. delete bot (also covered by tearDown but verify return shape)
        $deletedBot = $this->client->delete($botname);
        $this->assertSame('ok', $deletedBot->status, 'delete should return status=ok');

        // bot already gone — drop from cleanup queue (no-op if tearDown runs)
    }
}
