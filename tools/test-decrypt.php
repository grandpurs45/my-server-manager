<?php
require_once __DIR__ . '/../includes/crypto.php';

$crypted = 'VK9Qdlt1HMidE3vri7dz+7D8Kk4DsssssuubUBEzmtM=';

try {
    $decrypted = decrypt($crypted);
    if ($decrypted === false) {
        echo "❌ openssl_decrypt a échoué\n";
    } else {
        echo "✅ Déchiffré : $decrypted\n";
    }
} catch (Exception $e) {
    echo "❌ Exception : " . $e->getMessage() . "\n";
}
