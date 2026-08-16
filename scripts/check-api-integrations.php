#!/usr/bin/env php
<?php
declare(strict_types=1);

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\ApiIntegrationManager;
use MSM\CheckRunTracker;
use MSM\SettingsManager;

$now = new DateTimeImmutable('now');
$force = in_array('--force', $argv ?? [], true);
$settingsManager = new SettingsManager($pdo);
$tracker = new CheckRunTracker($settingsManager, 'api_integrations');
$tracker->start();

$intervalMinutes = max(1, (int) ($settingsManager->get('api_integrations', 'check_interval_minutes') ?? 1));
$lastRunRaw = $settingsManager->get('api_integrations', 'check_last_run_at');
if (!$force && $lastRunRaw !== null) {
    try {
        $elapsed = $now->getTimestamp() - (new DateTimeImmutable($lastRunRaw))->getTimestamp();
        if ($elapsed < $intervalMinutes * 60) {
            $message = "Collecte API sautee ({$elapsed}s ecoulees, intervalle {$intervalMinutes} min).";
            $tracker->skip($message);
            echo '[' . $now->format('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
            exit(0);
        }
    } catch (Throwable) {
        // Une date invalide force une nouvelle execution.
    }
}

try {
    $summary = (new ApiIntegrationManager($pdo))->collectDue();
    $message = sprintf(
        'Collecte API terminee : %d source(s), %d metrique(s), %d erreur(s).',
        $summary['sources'],
        $summary['metrics'],
        $summary['errors']
    );
    if ($summary['errors'] > 0) {
        throw new RuntimeException($message);
    }
    $tracker->success($message);
    echo '[' . $now->format('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
} catch (Throwable $exception) {
    $tracker->failure($exception);
    fwrite(STDERR, '[' . $now->format('Y-m-d H:i:s') . '] Erreur collecte API : ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
