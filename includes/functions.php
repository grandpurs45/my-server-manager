<?php
require_once __DIR__ . '/network.php';

function isHostUp(string $ip): bool {
    if (!function_exists('exec')) {
        return false;
    }

    $pingCmd = msmBuildPingCommand($ip, 1);
    if ($pingCmd === null) {
        return false;
    }

    exec($pingCmd, $output, $resultCode);

    return $resultCode === 0;
}

function getRemoteOS(string $ip): ?string {
    if (!function_exists('exec')) {
        return null;
    }

    $host = msmNormalizeHost($ip);
    if ($host === null) {
        return null;
    }

    $user = 'grandpurs45';
    $sshTarget = escapeshellarg($user . '@' . $host);
    $sshCmd = "ssh -o ConnectTimeout=2 -o BatchMode=yes $sshTarget cat /etc/os-release";
    exec($sshCmd, $output, $code);

    if ($code !== 0) {
        return null;
    }

    foreach ($output as $line) {
        if (str_starts_with($line, 'PRETTY_NAME=')) {
            return trim(trim(explode('=', $line, 2)[1]), '"');
        }
    }

    return null;
}

function msmDisplayDate(?string $date, string $fallback = 'Jamais', ?string $format = null): string
{
    if ($date === null || trim($date) === '') {
        return $fallback;
    }

    if ($format === null) {
        $format = msmDateDisplayFormat();
    }

    try {
        return (new DateTimeImmutable($date))->format($format);
    } catch (Throwable) {
        return $date;
    }
}

function msmDateDisplayFormat(): string
{
    $format = null;

    if (isset($GLOBALS['settings']) && is_object($GLOBALS['settings']) && method_exists($GLOBALS['settings'], 'get')) {
        try {
            $format = trim((string) ($GLOBALS['settings']->get('msm', 'date_display_format') ?? ''));
        } catch (Throwable) {
            $format = null;
        }
    }

    return $format !== null && $format !== '' ? $format : 'd/m/Y H:i:s';
}
