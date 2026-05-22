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
