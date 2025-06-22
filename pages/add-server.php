<?php
require_once __DIR__ . '/../vendor/autoload.php';   // phpseclib
require_once __DIR__ . '/../autoloader.php';        // ton autoloader POO
require_once __DIR__ . '/../includes/db.php';       // connexion PDO

$serveur = new Serveur($_POST);
$sshStatus = $serveur->testSSHConnection() ? 'success' : 'fail';

if ($serveur->save($pdo, $sshStatus)) {
    header('Location: serveurs.php?success=1');
} else {
    header('Location: serveurs.php?error=1');
}
exit;