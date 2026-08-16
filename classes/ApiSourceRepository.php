<?php
namespace MSM;

use InvalidArgumentException;
use PDO;

final class ApiSourceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listSources(): array
    {
        return $this->pdo->query("\n            SELECT s.*,\n                   COUNT(DISTINCT r.id) AS resource_count,\n                   COUNT(DISTINCT m.id) AS metric_count,\n                   SUM(CASE WHEN m.enabled = 1 THEN 1 ELSE 0 END) AS enabled_metric_count\n            FROM api_sources s\n            LEFT JOIN api_resources r ON r.api_source_id = s.id AND r.missing_since IS NULL\n            LEFT JOIN api_metrics m ON m.api_resource_id = r.id\n            GROUP BY s.id\n            ORDER BY s.name\n        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id, bool $withCredentials = false): ?array
    {
        $stmt = $this->pdo->prepare("\n            SELECT s.*, c.username_encrypted, c.secret_encrypted\n            FROM api_sources s\n            JOIN api_credentials c ON c.id = s.credentials_id\n            WHERE s.id = ?\n        ");
        $stmt->execute([$id]);
        $source = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$source) {
            return null;
        }

        if ($withCredentials) {
            $source['username'] = decrypt((string) $source['username_encrypted']);
            $source['secret'] = decrypt((string) $source['secret_encrypted']);
        }
        unset($source['username_encrypted'], $source['secret_encrypted']);

        return $source;
    }

    public function create(array $data): int
    {
        $clean = $this->validate($data, true);
        $this->pdo->beginTransaction();
        try {
            $credential = $this->pdo->prepare('INSERT INTO api_credentials (username_encrypted, secret_encrypted) VALUES (?, ?)');
            $credential->execute([encrypt($clean['username']), encrypt($clean['secret'])]);
            $credentialId = (int) $this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare("\n                INSERT INTO api_sources\n                    (name, connector_type, protocol, hostname, port, credentials_id, verify_tls, timeout_seconds, discovery_interval_minutes, enabled, configuration_json)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)\n            ");
            $stmt->execute([
                $clean['name'], $clean['connector_type'], $clean['protocol'], $clean['hostname'],
                $clean['port'], $credentialId, $clean['verify_tls'], $clean['timeout_seconds'],
                $clean['discovery_interval_minutes'], '{}',
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function update(int $id, array $data): void
    {
        $existing = $this->find($id);
        if ($existing === null) {
            throw new InvalidArgumentException('Source API introuvable.');
        }
        $clean = $this->validate($data, false);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("\n                UPDATE api_sources SET name = ?, connector_type = ?, protocol = ?, hostname = ?, port = ?,\n                    verify_tls = ?, timeout_seconds = ?, discovery_interval_minutes = ? WHERE id = ?\n            ");
            $stmt->execute([
                $clean['name'], $clean['connector_type'], $clean['protocol'], $clean['hostname'],
                $clean['port'], $clean['verify_tls'], $clean['timeout_seconds'],
                $clean['discovery_interval_minutes'], $id,
            ]);
            if ($clean['username'] !== '' || $clean['secret'] !== '') {
                $credential = $this->pdo->prepare("\n                    UPDATE api_credentials SET\n                        username_encrypted = CASE WHEN ? <> '' THEN ? ELSE username_encrypted END,\n                        secret_encrypted = CASE WHEN ? <> '' THEN ? ELSE secret_encrypted END\n                    WHERE id = ?\n                ");
                $credential->execute([
                    $clean['username'], $clean['username'] !== '' ? encrypt($clean['username']) : '',
                    $clean['secret'], $clean['secret'] !== '' ? encrypt($clean['secret']) : '',
                    (int) $existing['credentials_id'],
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $source = $this->find($id);
        if ($source === null) {
            return;
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM api_sources WHERE id = ?')->execute([$id]);
            $this->pdo->prepare('DELETE FROM api_credentials WHERE id = ?')->execute([(int) $source['credentials_id']]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        if ($enabled) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM api_metrics m JOIN api_resources r ON r.id = m.api_resource_id WHERE r.api_source_id = ? AND m.enabled = 1");
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() === 0) {
                throw new InvalidArgumentException('Effectuez une decouverte et activez au moins une metrique avant la source.');
            }
        }
        $this->pdo->prepare('UPDATE api_sources SET enabled = ? WHERE id = ?')->execute([$enabled ? 1 : 0, $id]);
    }

    public function saveTestResult(int $id, array $result): void
    {
        $stmt = $this->pdo->prepare('UPDATE api_sources SET last_test_status = ?, last_test_message = ?, last_tested_at = NOW() WHERE id = ?');
        $stmt->execute([(string) ($result['status'] ?? 'error'), mb_substr((string) ($result['message'] ?? ''), 0, 500), $id]);
    }

    public function resourcesForSource(int $sourceId): array
    {
        $stmt = $this->pdo->prepare("\n            SELECT r.*, m.id AS metric_id, m.external_key, m.name AS metric_name, m.data_type, m.unit,\n                   m.enabled AS metric_enabled, m.collection_interval_minutes, m.last_value_json,\n                   m.last_status, m.last_collected_at\n            FROM api_resources r\n            LEFT JOIN api_metrics m ON m.api_resource_id = r.id\n            WHERE r.api_source_id = ? AND r.missing_since IS NULL\n            ORDER BY FIELD(r.resource_type, 'hub', 'camera', 'storage'), r.name, m.name\n        ");
        $stmt->execute([$sourceId]);
        $resources = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) $row['id'];
            if (!isset($resources[$id])) {
                $resources[$id] = $row;
                $resources[$id]['metadata'] = $this->decodeJson($row['metadata_json']);
                $resources[$id]['metrics'] = [];
            }
            if ($row['metric_id'] !== null) {
                $row['last_value'] = $this->decodeJson($row['last_value_json']);
                $resources[$id]['metrics'][] = $row;
            }
        }
        return array_values($resources);
    }

    public function recentSamplesForSource(int $sourceId, int $limitPerMetric = 10): array
    {
        $limitPerMetric = max(1, min(50, $limitPerMetric));
        $stmt = $this->pdo->prepare("\n            SELECT sample.api_metric_id, sample.value_json, sample.status, sample.collected_at\n            FROM api_metric_samples sample\n            JOIN api_metrics metric ON metric.id = sample.api_metric_id\n            JOIN api_resources resource ON resource.id = metric.api_resource_id\n            WHERE resource.api_source_id = ?\n              AND (\n                    SELECT COUNT(*)\n                    FROM api_metric_samples newer\n                    WHERE newer.api_metric_id = sample.api_metric_id\n                      AND (\n                            newer.collected_at > sample.collected_at\n                            OR (newer.collected_at = sample.collected_at AND newer.id > sample.id)\n                      )\n              ) < ?\n            ORDER BY sample.api_metric_id, sample.collected_at DESC, sample.id DESC\n        ");
        $stmt->execute([$sourceId, $limitPerMetric]);

        $samples = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $metricId = (int) $row['api_metric_id'];
            $row['value'] = $this->decodeJson($row['value_json']);
            unset($row['value_json']);
            $samples[$metricId][] = $row;
        }
        return $samples;
    }

    public function beginDiscovery(int $sourceId): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO api_discovery_runs (api_source_id, status) VALUES (?, 'running')");
        $stmt->execute([$sourceId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function persistDiscovery(
        int $sourceId,
        int $runId,
        array $result,
        int $rawRetentionDays,
        bool $enableNewMetrics = true
    ): void
    {
        $resources = $result['resources'] ?? [];
        $metricCount = 0;
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE api_resources SET missing_since = COALESCE(missing_since, NOW()) WHERE api_source_id = ?')->execute([$sourceId]);
            foreach ($resources as $resource) {
                $stmt = $this->pdo->prepare("\n                    INSERT INTO api_resources\n                        (api_source_id, external_id, resource_type, name, parent_external_id, metadata_json)\n                    VALUES (?, ?, ?, ?, ?, ?)\n                    ON DUPLICATE KEY UPDATE resource_type = VALUES(resource_type), name = VALUES(name),\n                        parent_external_id = VALUES(parent_external_id), metadata_json = VALUES(metadata_json),\n                        last_seen_at = NOW(), missing_since = NULL\n                ");
                $stmt->execute([
                    $sourceId, $resource['external_id'], $resource['resource_type'], $resource['name'],
                    $resource['parent_external_id'], $this->encodeJson($resource['metadata'] ?? []),
                ]);
                $resourceId = (int) $this->pdo->lastInsertId();
                if ($resourceId === 0) {
                    $find = $this->pdo->prepare('SELECT id FROM api_resources WHERE api_source_id = ? AND external_id = ?');
                    $find->execute([$sourceId, $resource['external_id']]);
                    $resourceId = (int) $find->fetchColumn();
                }

                foreach ($resource['metrics'] ?? [] as $metric) {
                    $metricCount++;
                    $metricStmt = $this->pdo->prepare("\n                        INSERT INTO api_metrics\n                            (api_resource_id, external_key, name, data_type, unit, enabled, collection_interval_minutes,\n                             last_value_json, last_raw_value_json, last_status, last_collected_at, next_collection_at, metadata_json)\n                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'success', NOW(), NOW(), ?)\n                        ON DUPLICATE KEY UPDATE name = VALUES(name), data_type = VALUES(data_type), unit = VALUES(unit),\n                            last_value_json = VALUES(last_value_json), last_raw_value_json = VALUES(last_raw_value_json),\n                            last_status = 'success', last_collected_at = NOW(), metadata_json = VALUES(metadata_json)\n                    ");
                    $metricStmt->execute([
                        $resourceId, $metric['external_key'], $metric['name'], $metric['data_type'], $metric['unit'],
                        $enableNewMetrics && !empty($metric['enabled']) ? 1 : 0,
                        (int) ($metric['collection_interval_minutes'] ?? 15),
                        $this->encodeJson($metric['value'] ?? null), $this->encodeJson($metric['raw_value'] ?? null),
                        $this->encodeJson($metric['metadata'] ?? []),
                    ]);
                }
            }

            $finish = $this->pdo->prepare("\n                UPDATE api_discovery_runs SET status = 'success', resource_count = ?, metric_count = ?, message = ?,\n                    raw_result_json = ?, finished_at = NOW(), raw_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY)\n                WHERE id = ?\n            ");
            $finish->execute([
                count($resources), $metricCount, mb_substr((string) ($result['message'] ?? ''), 0, 500),
                $this->encodeJson($result['raw'] ?? []), max(1, $rawRetentionDays), $runId,
            ]);
            $this->pdo->prepare("\n                UPDATE api_sources\n                SET last_discovered_at = NOW(),\n                    next_discovery_at = DATE_ADD(NOW(), INTERVAL discovery_interval_minutes MINUTE)\n                WHERE id = ?\n            ")->execute([$sourceId]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function failDiscovery(int $runId, \Throwable $exception): void
    {
        $stmt = $this->pdo->prepare("UPDATE api_discovery_runs SET status = 'error', message = ?, finished_at = NOW() WHERE id = ?");
        $stmt->execute([mb_substr($exception->getMessage(), 0, 500), $runId]);
    }

    public function updateMetricSelection(int $sourceId, array $enabledIds, array $intervals): void
    {
        $stmt = $this->pdo->prepare("\n            SELECT m.id FROM api_metrics m JOIN api_resources r ON r.id = m.api_resource_id WHERE r.api_source_id = ?\n        ");
        $stmt->execute([$sourceId]);
        $allowed = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $update = $this->pdo->prepare('UPDATE api_metrics SET enabled = ?, collection_interval_minutes = ?, next_collection_at = NOW() WHERE id = ?');
        foreach ($allowed as $metricId) {
            $interval = max(1, min(1440, (int) ($intervals[$metricId] ?? 15)));
            $update->execute([in_array($metricId, $enabledIds, true) ? 1 : 0, $interval, $metricId]);
        }
    }

    public function dueSources(): array
    {
        $stmt = $this->pdo->query("\n            SELECT s.id,\n                   CASE WHEN s.next_discovery_at IS NULL OR s.next_discovery_at <= NOW() THEN 1 ELSE 0 END AS discovery_due\n            FROM api_sources s\n            WHERE s.enabled = 1\n              AND (\n                    s.next_discovery_at IS NULL\n                    OR s.next_discovery_at <= NOW()\n                    OR EXISTS (\n                        SELECT 1\n                        FROM api_resources r\n                        JOIN api_metrics m ON m.api_resource_id = r.id AND m.enabled = 1\n                        WHERE r.api_source_id = s.id\n                          AND r.enabled = 1\n                          AND r.missing_since IS NULL\n                          AND (m.next_collection_at IS NULL OR m.next_collection_at <= NOW())\n                    )\n              )\n            ORDER BY s.id\n        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function storeCollection(int $sourceId, array $result): int
    {
        $stored = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($result['resources'] ?? [] as $resource) {
                $findResource = $this->pdo->prepare('SELECT id FROM api_resources WHERE api_source_id = ? AND external_id = ? AND enabled = 1');
                $findResource->execute([$sourceId, $resource['external_id']]);
                $resourceId = (int) $findResource->fetchColumn();
                if ($resourceId === 0) {
                    continue;
                }
                foreach ($resource['metrics'] ?? [] as $metric) {
                    $findMetric = $this->pdo->prepare("SELECT id, collection_interval_minutes FROM api_metrics WHERE api_resource_id = ? AND external_key = ? AND enabled = 1 AND (next_collection_at IS NULL OR next_collection_at <= NOW())");
                    $findMetric->execute([$resourceId, $metric['external_key']]);
                    $storedMetric = $findMetric->fetch(PDO::FETCH_ASSOC);
                    if (!$storedMetric) {
                        continue;
                    }
                    $valueJson = $this->encodeJson($metric['value'] ?? null);
                    $update = $this->pdo->prepare("UPDATE api_metrics SET last_value_json = ?, last_raw_value_json = ?, last_status = 'success', last_collected_at = NOW(), next_collection_at = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?");
                    $update->execute([$valueJson, $this->encodeJson($metric['raw_value'] ?? null), max(1, (int) $storedMetric['collection_interval_minutes']), (int) $storedMetric['id']]);
                    $this->pdo->prepare("INSERT INTO api_metric_samples (api_metric_id, value_json, status) VALUES (?, ?, 'success')")->execute([(int) $storedMetric['id'], $valueJson]);
                    $stored++;
                }
            }
            $this->pdo->prepare('UPDATE api_sources SET last_collected_at = NOW() WHERE id = ?')->execute([$sourceId]);
            $this->pdo->commit();
            return $stored;
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function validate(array $data, bool $credentialsRequired): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $connectorType = trim((string) ($data['connector_type'] ?? 'reolink'));
        $protocol = strtolower(trim((string) ($data['protocol'] ?? 'https')));
        $hostname = trim((string) ($data['hostname'] ?? ''));
        $username = trim((string) ($data['username'] ?? ''));
        $secret = (string) ($data['secret'] ?? '');
        if ($name === '' || $hostname === '') {
            throw new InvalidArgumentException('Le nom et l adresse de la source sont obligatoires.');
        }
        if (!in_array($protocol, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Le protocole doit etre HTTP ou HTTPS.');
        }
        if (str_contains($hostname, '://') || str_contains($hostname, '/') || (!filter_var($hostname, FILTER_VALIDATE_IP) && !preg_match('/^[a-z0-9][a-z0-9.-]*$/i', $hostname))) {
            throw new InvalidArgumentException('Adresse IP ou nom DNS invalide. Ne saisissez pas une URL complete.');
        }
        if ($credentialsRequired && ($username === '' || $secret === '')) {
            throw new InvalidArgumentException('L identifiant et le mot de passe sont obligatoires.');
        }

        return [
            'name' => $name,
            'connector_type' => $connectorType,
            'protocol' => $protocol,
            'hostname' => $hostname,
            'port' => max(1, min(65535, (int) ($data['port'] ?? ($protocol === 'https' ? 443 : 80)))),
            'username' => $username,
            'secret' => $secret,
            'verify_tls' => isset($data['verify_tls']) ? 1 : 0,
            'timeout_seconds' => max(1, min(60, (int) ($data['timeout_seconds'] ?? 15))),
            'discovery_interval_minutes' => max(5, min(10080, (int) ($data['discovery_interval_minutes'] ?? 60))),
        ];
    }

    private function encodeJson(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function decodeJson(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
