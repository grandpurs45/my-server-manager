<?php
require_once __DIR__ . '/../includes/bootstrap.php';

use MSM\AlertEngine;
use MSM\AlertRepository;
use MSM\SettingsManager;

$force = in_array('--force', $argv ?? [], true);
$settingsManager = new SettingsManager($pdo);
$intervalMinutes = (int) ($settingsManager->get('alerting', 'check_interval_minutes') ?? 5);
if ($intervalMinutes < 1) {
    $intervalMinutes = 5;
}

$lastRun = $settingsManager->get('alerting', 'check_last_run_at');
if (!$force && $lastRun) {
    $elapsedSeconds = time() - strtotime($lastRun);
    if ($elapsedSeconds >= 0 && $elapsedSeconds < $intervalMinutes * 60) {
        echo '[' . date('Y-m-d H:i:s') . '] Evaluation alerting sautee ('
            . (int) floor($elapsedSeconds / 60)
            . ' min ecoulees, intervalle '
            . $intervalMinutes
            . " min).\n";
        exit(0);
    }
}

$repository = new AlertRepository($pdo);
$engine = new AlertEngine($pdo, $repository);
$summary = $engine->run();

$settingsManager->set('alerting', 'check_last_run_at', date('Y-m-d H:i:s'));

echo '[' . date('Y-m-d H:i:s') . '] Alerting: '
    . 'opened=' . (int) $summary['opened']
    . ' updated=' . (int) $summary['updated']
    . ' refreshed=' . (int) ($summary['refreshed'] ?? 0)
    . ' resolved=' . (int) $summary['resolved']
    . ' active=' . (int) $summary['active']
    . "\n";
