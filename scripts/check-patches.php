#!/usr/bin/env php
<?php
declare(strict_types=1);

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\PatchManager;

$now = new DateTimeImmutable('now');

$manager = new PatchManager($pdo);
$manager->run();

echo '[' . $now->format('Y-m-d H:i:s') . "] Verification patch management terminee.\n";
