<?php
namespace MSM;

class SessionManager
{
    public const PERSISTENT_LIFETIME_SECONDS = 315360000;

    public static function start(int $timeoutMinutes = 60, string $cookiePath = '/'): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $timeoutMinutes = max(0, $timeoutMinutes);
        $gcLifetime = $timeoutMinutes === 0
            ? self::PERSISTENT_LIFETIME_SECONDS
            : max(1440, $timeoutMinutes * 60);
        $cookieLifetime = $timeoutMinutes === 0 ? self::PERSISTENT_LIFETIME_SECONDS : 0;

        ini_set('session.gc_maxlifetime', (string) $gcLifetime);
        ini_set('session.cookie_lifetime', (string) $cookieLifetime);
        self::configureStorage();

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => $cookieLifetime,
            'path' => $cookiePath,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('MSMSESSID');
        session_start();
    }

    public static function configureStorage(): void
    {
        $sessionDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'sessions';

        if (!is_dir($sessionDirectory) && !@mkdir($sessionDirectory, 0775, true) && !is_dir($sessionDirectory)) {
            return;
        }

        if (!is_writable($sessionDirectory)) {
            return;
        }

        ini_set('session.save_path', $sessionDirectory);
    }
}
