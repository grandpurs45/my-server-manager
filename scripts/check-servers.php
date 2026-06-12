#!/usr/bin/env php
<?php
declare(strict_types=1);

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\CheckRunTracker;
use MSM\ServerChecker;
use MSM\SettingsManager;

$force = in_array('--force', $argv ?? [], true);
$settingsManager = new SettingsManager($pdo);
$tracker = new CheckRunTracker($settingsManager, 'supervision');
$tracker->start();

$intervalMinutes = (int) ($settingsManager->get('supervision', 'check_interval_minutes') ?? 10);
if ($intervalMinutes < 1) {
    $intervalMinutes = 1;
}

$now = new DateTimeImmutable('now');
$lastRunRaw = $settingsManager->get('supervision', 'check_last_run_at');

if (!$force && $lastRunRaw !== null) {
    try {
        $lastRun = new DateTimeImmutable($lastRunRaw);
        $diffSeconds = $now->getTimestamp() - $lastRun->getTimestamp();

        if ($diffSeconds < $intervalMinutes * 60) {
            $message = "Verification sautee ({$diffSeconds}s ecoulees, intervalle {$intervalMinutes} min).";
            $tracker->skip($message);
            echo '[' . $now->format('Y-m-d H:i:s') . "] {$message}\n";
            exit(0);
        }
    } catch (Exception) {
        // Date invalide en base : on lance le check et on corrigera la valeur en fin d'execution.
    }
}

try {
    $checker = new ServerChecker($pdo, withMetrics: true);
    $checker->run();
    $tracker->success('Verification des serveurs terminee.');
    echo '[' . $now->format('Y-m-d H:i:s') . "] Verification des serveurs terminee.\n";
} catch (Throwable $e) {
    $tracker->failure($e);
    fwrite(STDERR, '[' . $now->format('Y-m-d H:i:s') . '] Erreur verification serveurs : ' . $e->getMessage() . "\n");
    exit(1);
}
