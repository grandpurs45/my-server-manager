#!/usr/bin/env php
<?php
declare(strict_types=1);

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\OsLifecycleManager;

$manager = new OsLifecycleManager($pdo);
$manager->run();

echo '[' . date('Y-m-d H:i:s') . "] Verification cycle de vie OS terminee.\n";
