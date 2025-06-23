<?php
require_once __DIR__ . '/../includes/db.php';

function isHostUp($hostname) {
    $output = null;
    $status = null;

    // Adapté à Windows & Linux (utilise ping avec 1 paquet)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("ping -n 1 $hostname", $output, $status);
    } else {
        exec("ping -c 1 $hostname", $output, $status);
    }

    return $status === 0;
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