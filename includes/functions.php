<?php



//fonction de PING
function isHostUp(string $ip): bool {
    if (!function_exists('exec')) {
        return false; // Sécurité minimale
    }

    $timeout = 1;
    $pingCmd = (stripos(PHP_OS, 'WIN') === 0)
        ? "ping -n 1 -w " . ($timeout * 1000) . " $ip"
        : "ping -c 1 -W $timeout $ip";
    //file_put_contents(__DIR__ . '/../msm-debug.log', "Ping vers : $ip\n", FILE_APPEND);
    exec($pingCmd, $output, $resultCode);

    return $resultCode === 0;
}

// fonction pour recuperer l'OS du serveur
function getRemoteOS(string $ip): ?string {
    $user = 'grandpurs45'; // À adapter
    $sshCmd = "ssh -o ConnectTimeout=2 -o BatchMode=yes {$user}@{$ip} cat /etc/os-release";
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

?>
