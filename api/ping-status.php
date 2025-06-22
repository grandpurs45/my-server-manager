<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$stmt = $pdo->query("SELECT id, ip_address FROM servers");
$servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = [];

foreach ($servers as $server) {
    $isUp = isHostUp($server['ip_address']);
    $result[$server['id']] = $isUp ? 'up' : 'down';
}

echo json_encode($result);