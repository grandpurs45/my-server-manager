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
    $data = base64_decode($encoded);
    $iv = substr($data, 0, 16);
    $ciphertext = substr($data, 16);
    return openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
}
