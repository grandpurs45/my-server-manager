<?php
require_once __DIR__ . '/../includes/bootstrap.php';

function getPingStats(string $ip): array {
    if (!function_exists('exec')) return ['status' => 'down', 'latency' => null];

    $timeout = 1;
    $isWindows = stripos(PHP_OS, 'WIN') === 0;

    $pingCmd = $isWindows
        ? "ping -n 1 -w " . ($timeout * 1000) . " $ip"
        : "ping -c 1 -W $timeout $ip";

    exec($pingCmd, $output, $resultCode);
    //print_r($output); // temporaire pour debug

    if ($resultCode !== 0) {
        return ['status' => 'down', 'latency' => null];
    }

    $latency = null;

    foreach ($output as $line) {
        if ($isWindows && preg_match('/temps[=<]?\\s*(\\d+)\\s*ms/i', $line, $matches)) {
            $latency = (int) $matches[1];
            break;
        } elseif (!$isWindows && preg_match('/time[=<]?(\d+\.?\d*)\s*ms/i', $line, $matches)) {
            $latency = round((float) $matches[1]);
            break;
        }
    }

    return ['status' => 'up', 'latency' => $latency];
}

$stmt = $pdo->query("SELECT id, hostname FROM servers");
$servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($servers as $server) {
    $ping = getPingStats($server['hostname']);
    $status = $ping['status'];
    $latency = $ping['latency'];
    $availability = ($status === 'up') ? 1 : 0;
    $now = date('Y-m-d H:i:s');

    // Mise à jour dans la table servers
    $update = $pdo->prepare("UPDATE servers SET status = :status, last_check = :last_check, latency = :latency WHERE id = :id");
    $update->execute([
        ':status' => $status,
        ':last_check' => $now,
        ':latency' => $latency,
        ':id' => $server['id']
    ]);

    // Insertion dans server_metrics : latence
    if ($latency !== null) {
        $insert = $pdo->prepare("INSERT INTO server_metrics (server_id, type, value, measured_at)
                                 VALUES (:server_id, 'ping', :value, :measured_at)");
        $insert->execute([
            ':server_id' => $server['id'],
            ':value' => $latency,
            ':measured_at' => $now
        ]);
    }

    // Insertion dans server_metrics : disponibilité
    $insert = $pdo->prepare("INSERT INTO server_metrics (server_id, type, value, measured_at)
                             VALUES (:server_id, 'availability', :value, :measured_at)");
    $insert->execute([
        ':server_id' => $server['id'],
        ':value' => $availability,
        ':measured_at' => $now
    ]);

// pour debug execution en mode console : php update-status.php
//echo "[{$server['hostname']}] status=$status latency=$latency\n";
}

header('Location: supervision.php?checked=1');
exit;
