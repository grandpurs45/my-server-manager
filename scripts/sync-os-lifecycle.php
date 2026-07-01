#!/usr/bin/env php
<?php
declare(strict_types=1);

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\EndOfLifeDateClient;
use MSM\OsLifecycleExternalSync;
use MSM\OsLifecycleRepository;
use MSM\SettingsManager;

$family = null;
foreach ($argv ?? [] as $argument) {
    if (str_starts_with($argument, '--family=')) {
        $family = trim(substr($argument, strlen('--family=')));
    }
}

try {
    $settingsManager = new SettingsManager($pdo);
    $externalProducts = OsLifecycleExternalSync::parseProductsText(
        $settingsManager->get('os_lifecycle', 'external_products')
    );
    $sync = new OsLifecycleExternalSync(
        new OsLifecycleRepository($pdo),
        new EndOfLifeDateClient(),
        $externalProducts
    );
    $summary = $sync->sync($family !== '' ? $family : null);

    foreach ($summary as $osFamily => $count) {
        echo '[' . $osFamily . '] references_imported=' . $count . PHP_EOL;
    }

    echo '[' . date('Y-m-d H:i:s') . "] Synchronisation cycle de vie OS terminee.\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] Erreur synchronisation cycle de vie OS : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
