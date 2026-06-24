#!/usr/bin/env php
<?php
declare(strict_types=1);

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\CheckRunTracker;
use MSM\HardwareHealthManager;
use MSM\SettingsManager;

$now = new DateTimeImmutable('now');
$force = in_array('--force', $argv ?? [], true);

$settingsManager = new SettingsManager($pdo);
$tracker = new CheckRunTracker($settingsManager, 'hardware_health');
$tracker->start();

$intervalMinutes = (int) ($settingsManager->get('hardware_health', 'check_interval_minutes') ?? 15);
if ($intervalMinutes < 1) {
    $intervalMinutes = 1;
}

$lastRunRaw = $settingsManager->get('hardware_health', 'check_last_run_at');
if (!$force && $lastRunRaw !== null) {
    try {
        $lastRun = new DateTimeImmutable($lastRunRaw);
        $diffSeconds = $now->getTimestamp() - $lastRun->getTimestamp();

        if ($diffSeconds < $intervalMinutes * 60) {
            $message = "Verification materielle sautee ({$diffSeconds}s ecoulees, intervalle {$intervalMinutes} min).";
            $tracker->skip($message);
            echo '[' . $now->format('Y-m-d H:i:s') . "] {$message}\n";
            exit(0);
        }
    } catch (Exception) {
        // Date invalide en base : le check est execute et la valeur sera corrigee.
    }
}

try {
    $manager = new HardwareHealthManager($pdo);
    $manager->run();
    $tracker->success('Verification materielle terminee.');
    echo '[' . $now->format('Y-m-d H:i:s') . "] Verification materielle terminee.\n";
} catch (Throwable $e) {
    $tracker->failure($e);
    fwrite(STDERR, '[' . $now->format('Y-m-d H:i:s') . '] Erreur materielle : ' . $e->getMessage() . "\n");
    exit(1);
}
