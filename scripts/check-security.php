#!/usr/bin/env php
<?php
declare(strict_types=1);

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\SecurityManager;
use MSM\SettingsManager;

$now = new DateTimeImmutable('now');
$force = in_array('--force', $argv ?? [], true);

$settingsManager = new SettingsManager($pdo);
$intervalHours = (int) ($settingsManager->get('security', 'check_interval_hours') ?? 24);
if ($intervalHours < 1) {
    $intervalHours = 1;
}

$lastRunRaw = $settingsManager->get('security', 'check_last_run_at');
if (!$force && $lastRunRaw !== null) {
    try {
        $lastRun = new DateTimeImmutable($lastRunRaw);
        $diffSeconds = $now->getTimestamp() - $lastRun->getTimestamp();

        if ($diffSeconds < $intervalHours * 3600) {
            $elapsedMinutes = (int) floor($diffSeconds / 60);
            echo '[' . $now->format('Y-m-d H:i:s') . "] Verification securite sautee ({$elapsedMinutes} min ecoulees, intervalle {$intervalHours} h).\n";
            exit(0);
        }
    } catch (Exception) {
        // Date invalide en base : on lance le check et on corrigera la valeur en fin d'execution.
    }
}

$manager = new SecurityManager($pdo);
$manager->run();

$settingsManager->set('security', 'check_last_run_at', $now->format('Y-m-d H:i:s'));

echo '[' . $now->format('Y-m-d H:i:s') . "] Verification securite terminee.\n";
