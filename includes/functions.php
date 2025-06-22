<?php
function isHostUp(string $ip): bool {
    // Pour Linux (ping -c 1), pour Windows utiliser -n 1
    $pingCmd = (strncasecmp(PHP_OS, 'WIN', 3) === 0)
        ? "ping -n 1 -w 500 $ip"
        : "ping -c 1 -W 1 $ip";

    exec($pingCmd, $output, $resultCode);
    return $resultCode === 0;
}

?>