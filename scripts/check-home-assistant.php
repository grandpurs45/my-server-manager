#!/usr/bin/env php
<?php
declare(strict_types=1);

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\CheckRunTracker;
use MSM\HomeAssistantManager;
use MSM\SettingsManager;

$now = new DateTimeImmutable('now');
$force = in_array('--force', $argv ?? [], true);

$settingsManager = new SettingsManager($pdo);
$tracker = new CheckRunTracker($settingsManager, 'home_assistant');
$tracker->start();

$intervalMinutes = (int) ($settingsManager->get('home_assistant', 'check_interval_minutes') ?? 15);
if ($intervalMinutes < 1) {
    $intervalMinutes = 1;
}

$lastRunRaw = $settingsManager->get('home_assistant', 'check_last_run_at');
if (!$force && $lastRunRaw !== null) {
    try {
        $lastRun = new DateTimeImmutable($lastRunRaw);
        $diffSeconds = $now->getTimestamp() - $lastRun->getTimestamp();

        if ($diffSeconds < $intervalMinutes * 60) {
            $message = "Verification Home Assistant sautee ({$diffSeconds}s ecoulees, intervalle {$intervalMinutes} min).";
            $tracker->skip($message);
            echo '[' . $now->format('Y-m-d H:i:s') . "] {$message}\n";
            exit(0);
        }
    } catch (Exception) {
        // Date invalide en base : le check est execute et la valeur sera corrigee.
    }
}

try {
    $manager = new HomeAssistantManager($pdo);
    $manager->run();
    $tracker->success('Verification Home Assistant terminee.');
    echo '[' . $now->format('Y-m-d H:i:s') . "] Verification Home Assistant terminee.\n";
} catch (Throwable $e) {
    $tracker->failure($e);
    fwrite(STDERR, '[' . $now->format('Y-m-d H:i:s') . '] Erreur Home Assistant : ' . $e->getMessage() . "\n");
    exit(1);
}
