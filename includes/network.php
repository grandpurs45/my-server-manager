<?php

function msmNormalizeHost(string $host): ?string {
    $host = trim($host);

    if ($host === '' || strlen($host) > 253) {
        return null;
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return $host;
    }

    $labels = explode('.', $host);
    foreach ($labels as $label) {
        if (
            $label === ''
            || strlen($label) > 63
            || !preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?$/', $label)
        ) {
            return null;
        }
    }

    return $host;
}

function msmBuildPingCommand(string $host, int $timeoutSeconds = 1): ?string {
    $normalizedHost = msmNormalizeHost($host);
    if ($normalizedHost === null) {
        return null;
    }

    $timeoutSeconds = max(1, $timeoutSeconds);
    $target = escapeshellarg($normalizedHost);

    if (stripos(PHP_OS, 'WIN') === 0) {
        return 'ping -n 1 -w ' . ($timeoutSeconds * 1000) . ' ' . $target;
    }

    return 'ping -c 1 -W ' . $timeoutSeconds . ' ' . $target;
}
