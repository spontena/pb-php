<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Spontena\PbPhp\Exception\ApiException;
use Spontena\PbPhp\Exception\PandorabotsException;
use Spontena\PbPhp\FileKind;
use Spontena\PbPhp\PBClient;

$host = getenv('PB_HOST') ?: 'https://api.pandorabots.com';
$appId = getenv('PB_APP_ID') ?: '';
$userKey = getenv('PB_USER_KEY') ?: '';
$botKey = getenv('PB_BOT_KEY') ?: null;
$botname = getenv('PB_BOTNAME') ?: 'pb-php-quickstart';

if ($appId === '' || $userKey === '') {
    fwrite(STDERR, "Set PB_APP_ID and PB_USER_KEY environment variables.\n");
    exit(1);
}

$pb = new PBClient(host: $host, appId: $appId, userKey: $userKey, botKey: $botKey);

try {
    echo "1. List bots\n";
    $bots = $pb->getBotsList();
    print_r($bots);

    echo "\n2. Create bot: {$botname}\n";
    print_r($pb->create($botname));

    echo "\n3. Upload AIML file\n";
    print_r($pb->upload(__DIR__ . '/../tests/Fixtures/sample.aiml', $botname));

    echo "\n4. Compile bot\n";
    print_r($pb->compile($botname));

    echo "\n5. Talk to bot\n";
    print_r($pb->talk('HELLO', $botname));

    if ($botKey !== null) {
        echo "\n5b. Anonymous talk (atalk, botkey auth)\n";
        print_r($pb->atalk('HELLO'));
    }

    echo "\n6. Delete uploaded file\n";
    print_r($pb->deleteBotFile('sample', FileKind::File, $botname));

    echo "\n7. Delete bot\n";
    print_r($pb->delete($botname));
} catch (ApiException $e) {
    fwrite(STDERR, sprintf("API error %d: %s\n", $e->getStatusCode(), $e->getResponseBody()));
    exit(2);
} catch (PandorabotsException $e) {
    fwrite(STDERR, "Client error: {$e->getMessage()}\n");
    exit(3);
}
