<?php
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../autoloader.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/crypto.php';

use MSM\SSHUtils;
use MSM\ServerChecker;

$name = trim($_POST['name'] ?? '');
$hostname = trim($_POST['hostname'] ?? '');
$ssh_port = isset($_POST['ssh_port']) && is_numeric($_POST['ssh_port']) ? (int) $_POST['ssh_port'] : 22;
$ssh_user = trim($_POST['ssh_user'] ?? '');
$ssh_password_clair = $_POST['ssh_password'] ?? '';
$ssh_password_encrypt = $ssh_password_clair ? encrypt($ssh_password_clair) : '';
$ssh_enabled = isset($_POST['ssh_enabled']) ? 1 : 0;

if (!$name || !$hostname) {
    $_SESSION['error'] = "Nom et adresse du serveur sont obligatoires.";
    header("Location: ../pages/serveurs.php");
    exit;
}

if ($ssh_enabled && (!$ssh_user || !$ssh_password_encrypt || !$ssh_port)) {
    $_SESSION['error'] = "Tous les champs SSH sont obligatoires si la connexion SSH est activée.";
    header("Location: ../pages/serveurs.php");
    exit;
}

if ($ssh_enabled) {
    $ssh_status = 'fail'; // par défaut
    $os = 'OS inconnu';

    try {
        $os_detected = SSHUtils::detectOS($hostname, $ssh_port, $ssh_user, $ssh_password_clair);
        if ($os_detected !== null) {
            $os = $os_detected;
            $ssh_status = 'success';
            $_SESSION['success'] = "Serveur ajouté avec succès.";
        } else {
            $_SESSION['error'] = "Connexion SSH impossible. Serveur ajouté sans détection d’OS.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur SSH : " . $e->getMessage();
    }
}

// ✅ Insérer le serveur dans tous les cas
$checker = new ServerChecker($pdo);
$pingResult = $checker->getPingStats($hostname); // ou $ip si tu préfères
$status = $pingResult['status'] === 'up' ? 'UP' : 'DOWN';
try {
    $stmt = $pdo->prepare("INSERT INTO servers (name, hostname, ssh_port, ssh_user, ssh_password, os, ssh_status, ssh_enabled, status)
                    VALUES (:name, :hostname, :ssh_port, :ssh_user, :ssh_password, :os, :ssh_status, :ssh_enabled, :status)");

    $stmt->execute([
        ':name'        => $name,
        ':hostname'    => $hostname,
        ':ssh_port'    => $ssh_port,
        ':ssh_user'    => $ssh_user,
        ':ssh_password'=> $ssh_password_encrypt,
        ':os'          => $os,
        ':ssh_status'  => $ssh_status,
        ':ssh_enabled' => $ssh_enabled,
        ':status' => $status
    ]);
        // ✅ confirmation dans tous les cas sauf si une erreur SSH a déjà été mise
    if (!isset($_SESSION['error'])) {
        $_SESSION['success'] = "Serveur ajouté avec succès.";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de l'ajout : " . $e->getMessage();
}

header("Location: ../pages/serveurs.php");
exit;
