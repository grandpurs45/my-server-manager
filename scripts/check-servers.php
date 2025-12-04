#!/usr/bin/env php
<?php
declare(strict_types=1);

// On se place à la racine du projet (au cas où le script est lancé depuis ailleurs)
chdir(__DIR__ . '/..');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\ServerChecker;
use MSM\SettingsManager;

// --- 1) Gestion de la fréquence via les paramètres MSM ---

$settingsManager = new SettingsManager($pdo);

// Fréquence en minutes (paramètre : supervision / check_interval_minutes)
$intervalMinutes = (int) ($settingsManager->get('supervision', 'check_interval_minutes') ?? 10);
if ($intervalMinutes < 1) {
    $intervalMinutes = 1; // sécurité : minimum 1 minute
}

// Récupération de la dernière exécution
$now = new \DateTimeImmutable('now');
$lastRunRaw = $settingsManager->get('supervision', 'check_last_run_at');

if ($lastRunRaw !== null) {
    try {
        $lastRun = new \DateTimeImmutable($lastRunRaw);
        $diffSeconds = $now->getTimestamp() - $lastRun->getTimestamp();

        // Si le dernier check est trop récent : on saute cette exécution
        if ($diffSeconds < $intervalMinutes * 60) {
            echo '[' . $now->format('Y-m-d H:i:s') . "] Vérification sautée (seulement {$diffSeconds}s écoulées, intervalle {$intervalMinutes} min).\n";
            exit(0);
        }
    } catch (\Exception $e) {
        // Si la date est invalide en base, on ignore et on lance quand même
    }
}

// --- 2) Lancement réel du check des serveurs ---

$checker = new ServerChecker($pdo, withMetrics: true);
$checker->run();

// On enregistre la date/heure de cette exécution
$settingsManager->set('supervision', 'check_last_run_at', $now->format('Y-m-d H:i:s'));

// Log final
echo '[' . $now->format('Y-m-d H:i:s') . "] Vérification des serveurs terminée.\n";
