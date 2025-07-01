<?php
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../autoloader.php';
require_once __DIR__ . '/../includes/functions.php';

use MSM\SSHUtils;

$name = trim($_POST['name'] ?? '');
$hostname = trim($_POST['hostname'] ?? '');
$port = (int) ($_POST['port'] ?? 22);
$ssh_user = trim($_POST['ssh_user'] ?? '');
$ssh_password = trim($_POST['ssh_password'] ?? '');

if (!$name || !$hostname || !$ssh_user || !$ssh_password) {
    $_SESSION['error'] = "Tous les champs sont obligatoires.";
    header("Location: ../pages/serveurs.php");
    exit;
}

try {
    $os = SSHUtils::detectOS($hostname, $port, $ssh_user, $ssh_password);
    $ssh_status = ($os === null) ? 'fail' : 'success';

    if ($os === null) {
        $os = 'OS inconnu';
        $_SESSION['error'] = "Connexion SSH impossible. Serveur ajouté sans détection d’OS.";
    } else {
        $_SESSION['success'] = "Serveur ajouté avec succès.";
    }

    $stmt = $pdo->prepare("INSERT INTO servers (name, hostname, port, ssh_user, ssh_password, os, ssh_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $hostname, $port, $ssh_user, $ssh_password, $os, $ssh_status]);

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de l'ajout : " . $e->getMessage();
}

header("Location: ../pages/serveurs.php");
exit;
