<?php
require_once __DIR__ . '/config.php';

function getSecretKey(): string {
    $envKey = msmEnv('MSM_SECRET_KEY');
    if (!empty($envKey)) {
        return $envKey;
    }

    $keyFile = __DIR__ . '/../msm_secret.key';
    if (file_exists($keyFile)) {
        return trim(file_get_contents($keyFile));
    }

    throw new Exception("Cle de chiffrement manquante. Configure MSM_SECRET_KEY dans .env.");
}

function encrypt(string $plaintext): string {
    $key = getSecretKey();
    $iv = random_bytes(16);
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $ciphertext);
}

function decrypt(string $encoded): string {
    $key = getSecretKey();

    $data = base64_decode($encoded, true);
    if ($data === false || strlen($data) < 17) {
        return $encoded;
    }

    $iv = substr($data, 0, 16);
    $ciphertext = substr($data, 16);

    $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

    if ($plaintext === false) {
        return $encoded;
    }

    return $plaintext;
}
