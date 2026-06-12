<?php
require_once __DIR__ . '/../includes/bootstrap.php';

use MSM\AlertEngine;
use MSM\AlertRepository;
use MSM\CheckRunTracker;
use MSM\SettingsManager;

$force = in_array('--force', $argv ?? [], true);
$settingsManager = new SettingsManager($pdo);
$tracker = new CheckRunTracker($settingsManager, 'alerting');
$tracker->start();

$intervalMinutes = (int) ($settingsManager->get('alerting', 'check_interval_minutes') ?? 5);
if ($intervalMinutes < 1) {
    $intervalMinutes = 5;
}

$lastRun = $settingsManager->get('alerting', 'check_last_run_at');
if (!$force && $lastRun) {
    $elapsedSeconds = time() - strtotime($lastRun);
    if ($elapsedSeconds >= 0 && $elapsedSeconds < $intervalMinutes * 60) {
        $message = 'Evaluation alerting sautee ('
            . (int) floor($elapsedSeconds / 60)
            . ' min ecoulees, intervalle '
            . $intervalMinutes
            . ' min).';
        $tracker->skip($message);
        echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        exit(0);
    }
}

try {
    $repository = new AlertRepository($pdo);
    $engine = new AlertEngine($pdo, $repository);
    $summary = $engine->run();

    $message = 'Alerting: '
        . 'opened=' . (int) ($summary['opened'] ?? 0)
        . ' updated=' . (int) ($summary['updated'] ?? 0)
        . ' refreshed=' . (int) ($summary['refreshed'] ?? 0)
        . ' resolved=' . (int) ($summary['resolved'] ?? 0)
        . ' active=' . (int) ($summary['active'] ?? 0);

    $tracker->success($message);
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
} catch (Throwable $e) {
    $tracker->failure($e);
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] Erreur alerting : ' . $e->getMessage() . "\n");
    exit(1);
}
