#!/usr/bin/env php
<?php
declare(strict_types=1);

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\CheckRunTracker;
use MSM\PatchManager;
use MSM\SettingsManager;

$now = new DateTimeImmutable('now');
$force = in_array('--force', $argv ?? [], true);

$settingsManager = new SettingsManager($pdo);
$tracker = new CheckRunTracker($settingsManager, 'patch_management');
$tracker->start();

$intervalHours = (int) ($settingsManager->get('patch_management', 'check_interval_hours') ?? 6);
if ($intervalHours < 1) {
    $intervalHours = 1;
}

$lastRunRaw = $settingsManager->get('patch_management', 'check_last_run_at');
if (!$force && $lastRunRaw !== null) {
    try {
        $lastRun = new DateTimeImmutable($lastRunRaw);
        $diffSeconds = $now->getTimestamp() - $lastRun->getTimestamp();

        if ($diffSeconds < $intervalHours * 3600) {
            $elapsedMinutes = (int) floor($diffSeconds / 60);
            $message = "Verification patch management sautee ({$elapsedMinutes} min ecoulees, intervalle {$intervalHours} h).";
            $tracker->skip($message);
            echo '[' . $now->format('Y-m-d H:i:s') . "] {$message}\n";
            exit(0);
        }
    } catch (Exception) {
        // Date invalide en base : on lance le check et on corrigera la valeur en fin d'execution.
    }
}

try {
    $manager = new PatchManager($pdo);
    $manager->run();
    $tracker->success('Verification patch management terminee.');
    echo '[' . $now->format('Y-m-d H:i:s') . "] Verification patch management terminee.\n";
} catch (Throwable $e) {
    $tracker->failure($e);
    fwrite(STDERR, '[' . $now->format('Y-m-d H:i:s') . '] Erreur patch management : ' . $e->getMessage() . "\n");
    exit(1);
}
