<?php

function msmEnsureSession(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function msmCsrfToken(): string {
    msmEnsureSession();

    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function msmCsrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(msmCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function msmCsrfIsValid(?string $token): bool {
    msmEnsureSession();

    return is_string($token)
        && isset($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $token);
}

function msmRequireValidCsrf(?string $redirect = null): void {
    if (msmCsrfIsValid($_POST['csrf_token'] ?? null)) {
        return;
    }

    if ($redirect !== null) {
        $_SESSION['error'] = 'Jeton de securite invalide. Merci de reessayer.';
        header("Location: $redirect");
        exit;
    }

    http_response_code(400);
    exit('Jeton de securite invalide.');
}
