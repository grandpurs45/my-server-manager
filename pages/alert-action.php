<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';

use MSM\AlertRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: alerts.php');
    exit;
}

$redirect = (string) ($_POST['redirect'] ?? 'alerts.php');
if (!str_starts_with($redirect, 'alerts.php')) {
    $redirect = 'alerts.php';
}

msmRequireValidCsrf($redirect);

$repository = new AlertRepository($pdo);
$alertId = max(0, (int) ($_POST['alert_id'] ?? 0));
$action = (string) ($_POST['action'] ?? '');
$comment = trim((string) ($_POST['comment'] ?? ''));
$userId = isset($currentUser['id']) ? (int) $currentUser['id'] : null;
$ok = false;

if ($alertId > 0) {
    $ok = match ($action) {
        'acknowledge' => $repository->acknowledgeAlert($alertId, $userId, $comment),
        'unacknowledge' => $repository->unacknowledgeAlert($alertId, $userId, $comment),
        'ignore' => $repository->ignoreAlert($alertId, $userId, $comment),
        'unignore' => $repository->unignoreAlert($alertId, $userId, $comment),
        default => false,
    };
}

$_SESSION[$ok ? 'success' : 'error'] = $ok
    ? 'Action appliquee sur l alerte.'
    : 'Action impossible sur cette alerte.';

header('Location: ' . $redirect);
exit;
