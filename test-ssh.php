<?php
$fp = @fsockopen('192.168.1.141', 22, $errno, $errstr, 5);
if ($fp) {
    echo "✅ Connexion SSH (port 22) possible";
    fclose($fp);
} else {
    echo "❌ Erreur $errno : $errstr";
}