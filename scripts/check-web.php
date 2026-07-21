#!/usr/bin/env php
<?php
declare(strict_types=1);

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\CheckRunTracker;
use MSM\SettingsManager;
use MSM\WebMonitoringManager;

$now = new DateTimeImmutable('now');
$force = in_array('--force', $argv ?? [], true);
$settingsManager = new SettingsManager($pdo);
$tracker = new CheckRunTracker($settingsManager, 'web_monitoring');
$tracker->start();

try {
    $results = (new WebMonitoringManager($pdo))->run($force);
    foreach ($results as $entry) {
        $target = $entry['target'];
        $result = $entry['result'];
        $status = !empty($result['success']) ? 'up' : 'down';
        $httpStatus = $result['http_status'] ?? '-';
        $duration = $result['total_ms'] !== null ? $result['total_ms'] . 'ms' : '-';
        echo '[' . $target['name'] . '] status=' . $status . ' http=' . $httpStatus . ' duration=' . $duration . PHP_EOL;
    }

    $message = count($results) . ' cible(s) URL controlee(s).';
    $tracker->success($message);
    echo '[' . $now->format('Y-m-d H:i:s') . '] Supervision URLs terminee. ' . $message . PHP_EOL;
} catch (Throwable $e) {
    $tracker->failure($e);
    fwrite(STDERR, '[' . $now->format('Y-m-d H:i:s') . '] Erreur supervision URLs : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
