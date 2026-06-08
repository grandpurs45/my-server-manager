<?php
namespace MSM;

use PDO;

class AlertRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getEnabledRules(): array
    {
        if (!$this->tableExists('alert_rules')) {
            return [];
        }

        $stmt = $this->pdo->query("
            SELECT rule_key, name, source, severity, enabled, threshold_value
            FROM alert_rules
            WHERE enabled = 1
            ORDER BY id ASC
        ");

        $rules = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rule) {
            $rules[$rule['rule_key']] = $rule;
        }

        return $rules;
    }

    public function getRules(): array
    {
        if (!$this->tableExists('alert_rules')) {
            return [];
        }

        $stmt = $this->pdo->query("
            SELECT rule_key, name, source, severity, enabled, threshold_value, updated_at
            FROM alert_rules
            ORDER BY source ASC, name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateRule(string $ruleKey, bool $enabled, string $severity, ?int $thresholdValue): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE alert_rules
            SET enabled = :enabled,
                severity = :severity,
                threshold_value = :threshold_value
            WHERE rule_key = :rule_key
        ");
        $stmt->execute([
            ':enabled' => $enabled ? 1 : 0,
            ':severity' => $severity,
            ':threshold_value' => $thresholdValue,
            ':rule_key' => $ruleKey,
        ]);
    }

    public function getActiveAlerts(array $filters = []): array
    {
        $filters['status'] = 'active';

        return $this->getAlerts($filters);
    }

    public function getAlerts(array $filters = []): array
    {
        if (!$this->tableExists('alerts')) {
            return [];
        }

        $where = [];
        $params = [];
        $status = $filters['status'] ?? 'active';

        if ($status !== 'all') {
            $where[] = 'a.status = :status';
            $params[':status'] = $status;
        }

        if (!empty($filters['severity'])) {
            $where[] = 'a.severity = :severity';
            $params[':severity'] = $filters['severity'];
        }

        if (!empty($filters['source'])) {
            $where[] = 'ar.source = :source';
            $params[':source'] = $filters['source'];
        }

        if ($where === []) {
            $where[] = '1 = 1';
        }

        $stmt = $this->pdo->prepare("
            SELECT
                a.*,
                ar.name AS rule_name,
                ar.source,
                s.name AS server_name,
                s.hostname,
                s.target_type
            FROM alerts a
            LEFT JOIN alert_rules ar ON ar.rule_key = a.rule_key
            LEFT JOIN servers s ON s.id = a.server_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                CASE a.severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END,
                a.last_seen_at DESC,
                a.id DESC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActiveAlertCounts(): array
    {
        if (!$this->tableExists('alerts')) {
            return ['total' => 0, 'critical' => 0, 'warning' => 0, 'info' => 0];
        }

        $counts = ['total' => 0, 'critical' => 0, 'warning' => 0, 'info' => 0];
        $stmt = $this->pdo->query("
            SELECT severity, COUNT(*) AS count
            FROM alerts
            WHERE status = 'active'
            GROUP BY severity
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $severity = $row['severity'] ?: 'info';
            $count = (int) $row['count'];
            $counts[$severity] = $count;
            $counts['total'] += $count;
        }

        return $counts;
    }

    public function syncCandidates(array $candidates, array $managedRuleKeys): array
    {
        $now = date('Y-m-d H:i:s');
        $seen = [];
        $opened = 0;
        $updated = 0;
        $refreshed = 0;
        $resolved = 0;

        $this->pdo->beginTransaction();

        try {
            foreach ($candidates as $candidate) {
                $key = $this->candidateKey($candidate->ruleKey, $candidate->serverId, $candidate->fingerprint);
                $seen[$key] = true;

                $active = $this->findActiveAlert($candidate);
                if ($active) {
                    $changed = $this->updateActiveAlert($active, $candidate, $now);
                    if ($changed) {
                        $this->createEvent((int) $active['id'], 'updated', $candidate->severity, $candidate->message, $now);
                        $updated++;
                    } else {
                        $refreshed++;
                    }
                    continue;
                }

                $alertId = $this->openAlert($candidate, $now);
                $this->createEvent($alertId, 'opened', $candidate->severity, $candidate->message, $now);
                $opened++;
            }

            foreach ($this->getActiveAlertsForRules($managedRuleKeys) as $alert) {
                $key = $this->candidateKey($alert['rule_key'], $alert['server_id'] !== null ? (int) $alert['server_id'] : null, $alert['fingerprint']);
                if (isset($seen[$key])) {
                    continue;
                }

                $this->resolveAlert((int) $alert['id'], $now);
                $this->createEvent((int) $alert['id'], 'resolved', $alert['severity'], 'Alerte resolue automatiquement.', $now);
                $resolved++;
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'opened' => $opened,
            'updated' => $updated,
            'refreshed' => $refreshed,
            'resolved' => $resolved,
            'active' => count($candidates),
        ];
    }

    private function findActiveAlert(AlertCandidate $candidate): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM alerts
            WHERE status = 'active'
              AND rule_key = :rule_key
              AND fingerprint = :fingerprint
              AND " . ($candidate->serverId === null ? 'server_id IS NULL' : 'server_id = :server_id') . "
            ORDER BY id DESC
            LIMIT 1
        ");
        $params = [
            ':rule_key' => $candidate->ruleKey,
            ':fingerprint' => $candidate->fingerprint,
        ];
        if ($candidate->serverId !== null) {
            $params[':server_id'] = $candidate->serverId;
        }
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    private function openAlert(AlertCandidate $candidate, string $now): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO alerts (
                rule_key,
                server_id,
                severity,
                status,
                title,
                message,
                fingerprint,
                first_seen_at,
                last_seen_at
            ) VALUES (
                :rule_key,
                :server_id,
                :severity,
                'active',
                :title,
                :message,
                :fingerprint,
                :first_seen_at,
                :last_seen_at
            )
        ");
        $stmt->execute([
            ':rule_key' => $candidate->ruleKey,
            ':server_id' => $candidate->serverId,
            ':severity' => $candidate->severity,
            ':title' => $candidate->title,
            ':message' => $candidate->message,
            ':fingerprint' => $candidate->fingerprint,
            ':first_seen_at' => $now,
            ':last_seen_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function updateActiveAlert(array $active, AlertCandidate $candidate, string $now): bool
    {
        $changed = ($active['severity'] ?? '') !== $candidate->severity
            || ($active['title'] ?? '') !== $candidate->title;

        $stmt = $this->pdo->prepare("
            UPDATE alerts
            SET severity = :severity,
                title = :title,
                message = :message,
                last_seen_at = :last_seen_at,
                occurrence_count = occurrence_count + 1
            WHERE id = :id
        ");
        $stmt->execute([
            ':severity' => $candidate->severity,
            ':title' => $candidate->title,
            ':message' => $candidate->message,
            ':last_seen_at' => $now,
            ':id' => (int) $active['id'],
        ]);

        return $changed;
    }

    private function resolveAlert(int $alertId, string $now): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE alerts
            SET status = 'resolved',
                resolved_at = :resolved_at,
                last_seen_at = :last_seen_at
            WHERE id = :id
        ");
        $stmt->execute([
            ':resolved_at' => $now,
            ':last_seen_at' => $now,
            ':id' => $alertId,
        ]);
    }

    private function createEvent(int $alertId, string $eventType, string $severity, string $message, string $now): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO alert_events (alert_id, event_type, severity, message, created_at)
            VALUES (:alert_id, :event_type, :severity, :message, :created_at)
        ");
        $stmt->execute([
            ':alert_id' => $alertId,
            ':event_type' => $eventType,
            ':severity' => $severity,
            ':message' => $message,
            ':created_at' => $now,
        ]);
    }

    private function getActiveAlertsForRules(array $ruleKeys): array
    {
        if ($ruleKeys === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ruleKeys), '?'));
        $stmt = $this->pdo->prepare("
            SELECT id, rule_key, server_id, severity, fingerprint
            FROM alerts
            WHERE status = 'active'
              AND rule_key IN ({$placeholders})
        ");
        $stmt->execute(array_values($ruleKeys));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function candidateKey(string $ruleKey, ?int $serverId, string $fingerprint): string
    {
        return $ruleKey . ':' . ($serverId ?? 'global') . ':' . $fingerprint;
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name'
        );
        $stmt->execute([':table_name' => $tableName]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
