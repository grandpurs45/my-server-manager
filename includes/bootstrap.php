<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/status_badges.php';
require_once __DIR__ . '/../vendor/autoload.php';

use MSM\SettingsManager;
use MSM\AuthManager;

// Initialiser les paramètres
$settings = new SettingsManager($pdo);
$debug = $settings->get('msm', 'debug_mode') === 'true';

// Constante globale
if (!defined('DEBUG')) {
    define('DEBUG', $debug);
}

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

$authManager = new AuthManager($pdo, $settings);
$currentUser = null;

if (PHP_SAPI !== 'cli') {
    if (!defined('MSM_AUTH_PUBLIC') || MSM_AUTH_PUBLIC !== true) {
        $authManager->requireLogin();
        $authManager->requireModule($authManager->inferModuleFromRequest());
    }

    $currentUser = $authManager->currentUser();
}
