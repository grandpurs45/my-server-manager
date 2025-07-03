<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use MSM\SettingsManager;

// Initialiser les paramètres
$settings = new SettingsManager($pdo);
$debug = $settings->get('msm', 'debug_mode') === 'true';

// Constante globale
define('DEBUG', $debug);

// Affichage des erreurs PHP si DEBUG actif
if (DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}