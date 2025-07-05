<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\ServerChecker;

$checker = new ServerChecker($pdo, withMetrics: true);
$checker->run();