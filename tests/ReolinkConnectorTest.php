<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\ReolinkConnector;

$transport = static function (string $url): string {
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return match ($query['cmd'] ?? '') {
        'Login' => json_encode([['cmd' => 'Login', 'code' => 0, 'value' => ['Token' => ['name' => 'fixture-token', 'leaseTime' => 3600]]]]),
        'GetDevInfo' => json_encode([['cmd' => 'GetDevInfo', 'code' => 0, 'value' => ['DevInfo' => [
            'name' => 'Home Hub Pro', 'model' => 'Home Hub Pro', 'firmVer' => 'v3.0.0', 'hardVer' => 'IPC_999', 'serial' => 'TEST-HUB-01',
        ]]]]),
        'GetChannelstatus' => json_encode([['cmd' => 'GetChannelstatus', 'code' => 0, 'value' => ['status' => [
            ['channel' => 0, 'name' => 'Entree', 'uid' => 'CAM-01', 'online' => 1, 'sleep' => 0],
        ]]]]),
        'GetHddInfo' => json_encode([['cmd' => 'GetHddInfo', 'code' => 0, 'value' => ['HddInfo' => [
            ['capacity' => 1000, 'size' => 250, 'format' => 1, 'mount' => 1, 'storageType' => 1],
        ]]]]),
        'GetBatteryInfo' => json_encode([['cmd' => 'GetBatteryInfo', 'code' => 0, 'value' => ['Battery' => [
            'batteryPercent' => 82, 'temperature' => 31.5, 'voltage' => 4.1, 'current' => -0.2, 'chargeStatus' => 0,
        ]]]]),
        'Logout' => json_encode([['cmd' => 'Logout', 'code' => 0, 'value' => ['rspCode' => 200]]]),
        default => throw new RuntimeException('Commande fixture inconnue : ' . ($query['cmd'] ?? '')),
    };
};

$source = [
    'protocol' => 'https', 'hostname' => 'reolink.test', 'port' => 443,
    'username' => 'fixture', 'secret' => 'fixture-secret', 'timeout_seconds' => 5, 'verify_tls' => 1,
];

$connector = new ReolinkConnector($transport);
$test = $connector->testConnection($source);
if (empty($test['success']) || ($test['identity']['model'] ?? '') !== 'Home Hub Pro') {
    throw new RuntimeException('Echec du test de connexion fixture.');
}

$discovery = $connector->discover($source);
if (count($discovery['resources'] ?? []) !== 3) {
    throw new RuntimeException('La fixture doit produire un hub, une camera et un stockage.');
}

$camera = array_values(array_filter($discovery['resources'], static fn (array $resource): bool => $resource['resource_type'] === 'camera'))[0] ?? null;
$battery = array_values(array_filter($camera['metrics'] ?? [], static fn (array $metric): bool => $metric['external_key'] === 'battery_percent'))[0] ?? null;
if (($battery['value'] ?? null) !== 82) {
    throw new RuntimeException('La batterie normalisee est incorrecte.');
}

echo "ReolinkConnectorTest: OK\n";
