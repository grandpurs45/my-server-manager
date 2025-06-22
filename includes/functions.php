<?php
//fonction de PING
function isHostUp(string $ip): bool {
    // Pour Linux (ping -c 1), pour Windows utiliser -n 1
    $pingCmd = (strncasecmp(PHP_OS, 'WIN', 3) === 0)
        ? "ping -n 1 -w 500 $ip"
        : "ping -c 1 -W 1 $ip";

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
