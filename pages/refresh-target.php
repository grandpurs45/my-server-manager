<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';

use MSM\OsLifecycleManager;
use MSM\PatchManager;
use MSM\SecurityManager;
use MSM\ServerChecker;
use MSM\HardwareHealthManager;
use MSM\HomeAssistantManager;

$serverId = isset($_POST['server_id']) ? (int) $_POST['server_id'] : 0;
$module = (string) ($_POST['module'] ?? '');
$redirect = 'details-cible.php?id=' . $serverId;

if ($serverId < 1) {
    $_SESSION['error'] = 'Cible invalide.';
    header('Location: serveurs.php');
    exit;
}

msmRequireValidCsrf($redirect);

$moduleRequirements = [
    'supervision' => 'supervision',
    'patch_management' => 'patch_management',
    'os_lifecycle' => 'patch_management',
    'security' => 'securite',
    'hardware_health' => 'supervision',
    'home_assistant' => 'supervision',
];

if (!isset($moduleRequirements[$module])) {
    $_SESSION['error'] = 'Module de refresh inconnu.';
    header('Location: ' . $redirect);
    exit;
}

if (!$authManager->userCan($moduleRequirements[$module])) {
    $_SESSION['error'] = 'Votre compte ne dispose pas du droit necessaire pour lancer ce refresh.';
    header('Location: ' . $redirect);
    exit;
}

$labels = [
    'supervision' => 'Supervision',
    'patch_management' => 'Patch Management',
    'os_lifecycle' => 'Cycle de vie OS',
    'security' => 'Securite',
    'hardware_health' => 'Sante materielle',
    'home_assistant' => 'Home Assistant',
];

try {
    ob_start();

    match ($module) {
        'supervision' => (new ServerChecker($pdo, withMetrics: true))->runForServerId($serverId),
        'patch_management' => (new PatchManager($pdo))->runForServerId($serverId),
        'os_lifecycle' => (new OsLifecycleManager($pdo))->runForServerId($serverId),
        'security' => (new SecurityManager($pdo))->runForServerId($serverId),
        'hardware_health' => (new HardwareHealthManager($pdo))->runForServerId($serverId),
        'home_assistant' => (new HomeAssistantManager($pdo))->runForServerId($serverId),
    };

    ob_end_clean();
    $_SESSION['success'] = 'Refresh ' . ($labels[$module] ?? $module) . ' termine.';
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    $_SESSION['error'] = 'Refresh ' . ($labels[$module] ?? $module) . ' impossible : ' . $e->getMessage();
}

header('Location: ' . $redirect);
exit;
