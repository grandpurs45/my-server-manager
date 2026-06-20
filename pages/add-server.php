<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/inventory_options.php';

use MSM\SSHUtils;
use MSM\ServerChecker;
use MSM\SettingsManager;

msmRequireValidCsrf('serveurs.php');

$name = trim($_POST['name'] ?? '');
$hostname = trim($_POST['hostname'] ?? '');
$targetType = trim($_POST['target_type'] ?? 'linux');
$environment = trim($_POST['environment'] ?? 'production');
$criticality = trim($_POST['criticality'] ?? 'medium');
$tags = trim($_POST['tags'] ?? '');
$collectionMethod = trim($_POST['collection_method'] ?? 'ssh');
$sshPort = isset($_POST['ssh_port']) && is_numeric($_POST['ssh_port']) ? (int) $_POST['ssh_port'] : 22;
$sshUser = trim($_POST['ssh_user'] ?? '');
$sshPasswordPlain = $_POST['ssh_password'] ?? '';
$sshPasswordEncrypted = $sshPasswordPlain ? encrypt($sshPasswordPlain) : '';
$sshEnabled = isset($_POST['ssh_enabled']) ? 1 : 0;
$securityEnabled = isset($_POST['security_enabled']) ? 1 : 0;
$patchManagementEnabled = isset($_POST['patch_management_enabled']) ? 1 : 0;

$sshStatus = 'fail';
$os = 'OS inconnu';

$settingsManager = new SettingsManager($pdo);
$targetTypes = msmInventoryOptions($settingsManager, 'target_types');
$environments = msmInventoryOptions($settingsManager, 'environments');
$criticalities = msmInventoryOptions($settingsManager, 'criticalities');
$collectionMethods = msmInventoryOptions($settingsManager, 'collection_methods');

$targetType = msmInventoryNormalizeSelected($targetType, $targetTypes, array_key_first($targetTypes) ?: 'other');
$environment = msmInventoryNormalizeSelected($environment, $environments, array_key_first($environments) ?: 'other');
$criticality = msmInventoryNormalizeSelected($criticality, $criticalities, array_key_first($criticalities) ?: 'medium');
$collectionMethod = msmInventoryNormalizeSelected($collectionMethod, $collectionMethods, array_key_first($collectionMethods) ?: 'manual');

if (!$name || !$hostname) {
    $_SESSION['error'] = 'Nom et adresse du serveur sont obligatoires.';
    header('Location: serveurs.php');
    exit;
}

if ($sshEnabled && (!$sshUser || !$sshPasswordEncrypted || !$sshPort)) {
    $_SESSION['error'] = 'Tous les champs SSH sont obligatoires si la connexion SSH est activee.';
    header('Location: serveurs.php');
    exit;
}

if ($sshEnabled) {
    try {
        $detectedOs = SSHUtils::detectOS($hostname, $sshPort, $sshUser, $sshPasswordPlain);
        if ($detectedOs !== null) {
            $os = $detectedOs;
            $sshStatus = 'success';
            $_SESSION['success'] = 'Serveur ajoute avec succes.';
        } else {
            $_SESSION['error'] = "Connexion SSH impossible. Serveur ajoute sans detection d'OS.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erreur SSH : ' . $e->getMessage();
    }
}

$checker = new ServerChecker($pdo);
$pingResult = $checker->getPingStats($hostname);
$status = $pingResult['status'] === 'up' ? 'up' : 'down';

try {
    $stmt = $pdo->prepare("
        INSERT INTO servers (
            name, hostname, target_type, environment, criticality, tags, collection_method,
            ssh_port, ssh_user, ssh_password, os, ssh_status, ssh_enabled, security_enabled,
            patch_management_enabled, status
        )
        VALUES (
            :name, :hostname, :target_type, :environment, :criticality, :tags, :collection_method,
            :ssh_port, :ssh_user, :ssh_password, :os, :ssh_status, :ssh_enabled, :security_enabled,
            :patch_management_enabled, :status
        )
    ");

    $stmt->execute([
        ':name' => $name,
        ':hostname' => $hostname,
        ':target_type' => $targetType,
        ':environment' => $environment,
        ':criticality' => $criticality,
        ':tags' => $tags !== '' ? $tags : null,
        ':collection_method' => $collectionMethod,
        ':ssh_port' => $sshPort,
        ':ssh_user' => $sshUser,
        ':ssh_password' => $sshPasswordEncrypted,
        ':os' => $os,
        ':ssh_status' => $sshStatus,
        ':ssh_enabled' => $sshEnabled,
        ':security_enabled' => $securityEnabled,
        ':patch_management_enabled' => $patchManagementEnabled,
        ':status' => $status,
    ]);

    if (!isset($_SESSION['error'])) {
        $_SESSION['success'] = 'Serveur ajoute avec succes.';
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de l'ajout : " . $e->getMessage();
}

header('Location: serveurs.php');
exit;
