<?php
require_once __DIR__ . '/../includes/db.php';

function isHostUp(string $ip): bool {
    if (!function_exists('exec')) return false;

    $timeout = 1;
    $pingCmd = (stripos(PHP_OS, 'WIN') === 0)
        ? "ping -n 1 -w " . ($timeout * 1000) . " $ip"
        : "ping -c 1 -W $timeout $ip";

    exec($pingCmd, $output, $resultCode);
    /* si besoin de logs
    if ($resultCode !== 0) {
        error_log("Ping failed for $ip");
        return false;
    }*/
    // Ne pas parser le texte, juste le code de retour
    return $resultCode === 0;
}

$stmt = $pdo->query("SELECT id, hostname FROM servers");
$servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($servers as $server) {
    $isUp = isHostUp($server['hostname']);
    $status = $isUp ? 'up' : 'down';
    $now = date('Y-m-d H:i:s');

    $update = $pdo->prepare("UPDATE servers SET status = :status, last_check = :last_check WHERE id = :id");
    $update->execute([
        ':status' => $status,
        ':last_check' => $now,
        ':id' => $server['id']
    ]);

    echo "[{$server['hostname']}] => $status\n";
}