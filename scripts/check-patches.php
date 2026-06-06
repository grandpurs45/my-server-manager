#!/usr/bin/env php
<?php
declare(strict_types=1);

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\PatchManager;
use MSM\SettingsManager;

$now = new DateTimeImmutable('now');

$settingsManager = new SettingsManager($pdo);
$intervalHours = (int) ($settingsManager->get('patch_management', 'check_interval_hours') ?? 6);
if ($intervalHours < 1) {
    $intervalHours = 1;
}

$lastRunRaw = $settingsManager->get('patch_management', 'check_last_run_at');
if ($lastRunRaw !== null) {
    try {
        $lastRun = new DateTimeImmutable($lastRunRaw);
        $diffSeconds = $now->getTimestamp() - $lastRun->getTimestamp();

        if ($diffSeconds < $intervalHours * 3600) {
            $elapsedMinutes = (int) floor($diffSeconds / 60);
            echo '[' . $now->format('Y-m-d H:i:s') . "] Verification patch management sautee ({$elapsedMinutes} min ecoulees, intervalle {$intervalHours} h).\n";
            exit(0);
        }
    } catch (Exception $e) {
        // Date invalide en base : on lance le check et on corrigera la valeur en fin d'execution.
    }
}

$manager = new PatchManager($pdo);
$manager->run();

$settingsManager->set('patch_management', 'check_last_run_at', $now->format('Y-m-d H:i:s'));

echo '[' . $now->format('Y-m-d H:i:s') . "] Verification patch management terminee.\n";
