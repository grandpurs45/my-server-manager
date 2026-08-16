<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\ApiSourceRepository;

$repository = new ApiSourceRepository($pdo);
$sourceId = 0;

try {
    $sourceId = $repository->create([
        'name' => 'MSM API fixture ' . bin2hex(random_bytes(4)),
        'connector_type' => 'reolink',
        'protocol' => 'https',
        'hostname' => 'reolink.fixture.local',
        'port' => 443,
        'username' => 'fixture-user',
        'secret' => 'fixture-secret',
        'verify_tls' => '1',
        'timeout_seconds' => 5,
        'discovery_interval_minutes' => 60,
    ]);

    $source = $repository->find($sourceId, true);
    if (($source['username'] ?? '') !== 'fixture-user' || ($source['secret'] ?? '') !== 'fixture-secret') {
        throw new RuntimeException('Le dechiffrement des identifiants de test a echoue.');
    }

    $runId = $repository->beginDiscovery($sourceId);
    $repository->persistDiscovery($sourceId, $runId, [
        'message' => 'Fixture',
        'raw' => ['redacted' => true],
        'resources' => [[
            'external_id' => 'fixture:hub:1',
            'resource_type' => 'hub',
            'name' => 'Fixture Hub',
            'parent_external_id' => null,
            'metadata' => ['model' => 'Fixture'],
            'metrics' => [[
                'external_key' => 'api_available',
                'name' => 'API disponible',
                'data_type' => 'BOOLEAN',
                'unit' => null,
                'value' => true,
                'raw_value' => 1,
                'enabled' => true,
                'collection_interval_minutes' => 15,
                'metadata' => [],
            ]],
        ]],
    ], 7);

    $resources = $repository->resourcesForSource($sourceId);
    if (count($resources) !== 1 || count($resources[0]['metrics'] ?? []) !== 1) {
        throw new RuntimeException('La persistance de la decouverte est incorrecte.');
    }

    $metricId = (int) $resources[0]['metrics'][0]['metric_id'];
    $pdo->prepare("INSERT INTO api_metric_samples (api_metric_id, value_json, status) VALUES (?, 'true', 'success')")->execute([$metricId]);
    $history = $repository->recentSamplesForSource($sourceId);
    if (($history[$metricId][0]['value'] ?? null) !== true || empty($history[$metricId][0]['collected_at'])) {
        throw new RuntimeException('L historique recent des metriques est incorrect.');
    }

    $source = $repository->find($sourceId);
    if (empty($source['next_discovery_at'])) {
        throw new RuntimeException('La prochaine redecouverte n a pas ete planifiee.');
    }

    $repository->setEnabled($sourceId, true);
    $pdo->prepare('UPDATE api_sources SET next_discovery_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')->execute([$sourceId]);
    $dueSources = $repository->dueSources();
    $dueSource = array_values(array_filter($dueSources, static fn (array $item): bool => (int) $item['id'] === $sourceId))[0] ?? null;
    if ($dueSource === null || (int) $dueSource['discovery_due'] !== 1) {
        throw new RuntimeException('La source n est pas eligible a la redecouverte automatique.');
    }

    $secondRunId = $repository->beginDiscovery($sourceId);
    $repository->persistDiscovery($sourceId, $secondRunId, [
        'message' => 'Fixture avec nouvelle camera',
        'raw' => ['redacted' => true],
        'resources' => [[
            'external_id' => 'fixture:hub:1',
            'resource_type' => 'hub',
            'name' => 'Fixture Hub',
            'parent_external_id' => null,
            'metadata' => ['model' => 'Fixture'],
            'metrics' => [[
                'external_key' => 'api_available',
                'name' => 'API disponible',
                'data_type' => 'BOOLEAN',
                'unit' => null,
                'value' => true,
                'raw_value' => 1,
                'enabled' => true,
                'collection_interval_minutes' => 15,
                'metadata' => [],
            ]],
        ], [
            'external_id' => 'fixture:camera:2',
            'resource_type' => 'camera',
            'name' => 'Nouvelle camera',
            'parent_external_id' => 'fixture:hub:1',
            'metadata' => ['channel' => 2],
            'metrics' => [[
                'external_key' => 'online',
                'name' => 'En ligne',
                'data_type' => 'BOOLEAN',
                'unit' => null,
                'value' => true,
                'raw_value' => 1,
                'enabled' => true,
                'collection_interval_minutes' => 5,
                'metadata' => [],
            ]],
        ]],
    ], 7, false);

    $resources = $repository->resourcesForSource($sourceId);
    if (count($resources) !== 2) {
        throw new RuntimeException('La nouvelle camera n a pas ete rattachee automatiquement a la source.');
    }
    $camera = array_values(array_filter($resources, static fn (array $resource): bool => $resource['resource_type'] === 'camera'))[0] ?? null;
    if ($camera === null || (int) ($camera['metrics'][0]['metric_enabled'] ?? 1) !== 0) {
        throw new RuntimeException('Les metriques d une nouvelle camera doivent attendre la validation utilisateur.');
    }

    echo "ApiIntegrationRepositoryTest: OK\n";
} finally {
    if ($sourceId > 0) {
        $repository->delete($sourceId);
    }
}
