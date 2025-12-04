<?php

function getSecretKey(): string {
    $keyFile = __DIR__ . '/../msm_secret.key';
    if (!file_exists($keyFile)) {
        throw new Exception("Clé de chiffrement manquante : msm_secret.key");
    }
    return trim(file_get_contents($keyFile));
}

function encrypt(string $plaintext): string {
    $key = getSecretKey();
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $ciphertext);
}

function decrypt(string $encoded): string {
    $key = getSecretKey();

    // Décodage strict : si ce n'est pas du base64 valide, on ne tente rien
    $data = base64_decode($encoded, true);
    if ($data === false || strlen($data) < 17) {
        // Cas "legacy" ou non chiffré : on renvoie la valeur originale telle quelle
        return $encoded;
    }

    $iv = substr($data, 0, 16);
    $ciphertext = substr($data, 16);

    $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

    // Si le déchiffrement échoue (clé différente, données corrompues, etc.),
    // on renvoie aussi la valeur d'origine plutôt que de faire planter/avertir.
    if ($plaintext === false) {
        return $encoded;
    }

    return $plaintext;
}
