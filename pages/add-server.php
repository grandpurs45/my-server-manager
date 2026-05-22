<?php
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../autoloader.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/csrf.php';

use MSM\SSHUtils;
use MSM\ServerChecker;

msmRequireValidCsrf('../pages/serveurs.php');

$name = trim($_POST['name'] ?? '');
$hostname = trim($_POST['hostname'] ?? '');
$sshPort = isset($_POST['ssh_port']) && is_numeric($_POST['ssh_port']) ? (int) $_POST['ssh_port'] : 22;
$sshUser = trim($_POST['ssh_user'] ?? '');
$sshPasswordPlain = $_POST['ssh_password'] ?? '';
$sshPasswordEncrypted = $sshPasswordPlain ? encrypt($sshPasswordPlain) : '';
$sshEnabled = isset($_POST['ssh_enabled']) ? 1 : 0;

$sshStatus = 'fail';
$os = 'OS inconnu';

if (!$name || !$hostname) {
    $_SESSION['error'] = 'Nom et adresse du serveur sont obligatoires.';
    header('Location: ../pages/serveurs.php');
    exit;
}

if ($sshEnabled && (!$sshUser || !$sshPasswordEncrypted || !$sshPort)) {
    $_SESSION['error'] = 'Tous les champs SSH sont obligatoires si la connexion SSH est activee.';
    header('Location: ../pages/serveurs.php');
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
        INSERT INTO servers (name, hostname, ssh_port, ssh_user, ssh_password, os, ssh_status, ssh_enabled, status)
        VALUES (:name, :hostname, :ssh_port, :ssh_user, :ssh_password, :os, :ssh_status, :ssh_enabled, :status)
    ");

    $stmt->execute([
        ':name' => $name,
        ':hostname' => $hostname,
        ':ssh_port' => $sshPort,
        ':ssh_user' => $sshUser,
        ':ssh_password' => $sshPasswordEncrypted,
        ':os' => $os,
        ':ssh_status' => $sshStatus,
        ':ssh_enabled' => $sshEnabled,
        ':status' => $status,
    ]);

    if (!isset($_SESSION['error'])) {
        $_SESSION['success'] = 'Serveur ajoute avec succes.';
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de l'ajout : " . $e->getMessage();
}

header('Location: ../pages/serveurs.php');
exit;
