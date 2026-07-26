<?php
namespace MSM;

use PDO;

final class NotificationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getChannels(): array
    {
        if (!$this->tableExists('notification_channels')) {
            return [];
        }

        return $this->pdo->query("
            SELECT id, name, channel_type, enabled, minimum_severity,
                   notify_on_open, notify_on_resolve, created_at, updated_at
            FROM notification_channels
            ORDER BY name ASC, id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findChannel(int $channelId): ?array
    {
        if (!$this->tableExists('notification_channels')) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM notification_channels WHERE id = ?');
        $stmt->execute([$channelId]);
        $channel = $stmt->fetch(PDO::FETCH_ASSOC);

        return $channel ?: null;
    }

    public function saveChannel(?int $channelId, array $data): int
    {
        if ($channelId !== null) {
            $current = $this->findChannel($channelId);
            if ($current === null) {
                throw new \InvalidArgumentException('Canal de notification introuvable.');
            }

            $endpoint = trim((string) ($data['endpoint_encrypted'] ?? ''));
            if ($endpoint === '') {
                $endpoint = (string) $current['endpoint_encrypted'];
            }

            $stmt = $this->pdo->prepare("
                UPDATE notification_channels
                SET name = :name,
                    channel_type = :channel_type,
                    endpoint_encrypted = :endpoint_encrypted,
                    enabled = :enabled,
                    minimum_severity = :minimum_severity,
                    notify_on_open = :notify_on_open,
                    notify_on_resolve = :notify_on_resolve
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $data['name'],
                ':channel_type' => $data['channel_type'],
                ':endpoint_encrypted' => $endpoint,
                ':enabled' => $data['enabled'],
                ':minimum_severity' => $data['minimum_severity'],
                ':notify_on_open' => $data['notify_on_open'],
                ':notify_on_resolve' => $data['notify_on_resolve'],
                ':id' => $channelId,
            ]);

            return $channelId;
        }

        $startsAfterEventId = $this->tableExists('alert_events')
            ? (int) $this->pdo->query('SELECT COALESCE(MAX(id), 0) FROM alert_events')->fetchColumn()
            : 0;
        $stmt = $this->pdo->prepare("
            INSERT INTO notification_channels (
                name, channel_type, endpoint_encrypted, enabled, minimum_severity,
                notify_on_open, notify_on_resolve, starts_after_event_id
            ) VALUES (
                :name, :channel_type, :endpoint_encrypted, :enabled, :minimum_severity,
                :notify_on_open, :notify_on_resolve, :starts_after_event_id
            )
        ");
        $stmt->execute([
            ':name' => $data['name'],
            ':channel_type' => $data['channel_type'],
            ':endpoint_encrypted' => $data['endpoint_encrypted'],
            ':enabled' => $data['enabled'],
            ':minimum_severity' => $data['minimum_severity'],
            ':notify_on_open' => $data['notify_on_open'],
            ':notify_on_resolve' => $data['notify_on_resolve'],
            ':starts_after_event_id' => $startsAfterEventId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteChannel(int $channelId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM notification_channels WHERE id = ?');
        $stmt->execute([$channelId]);

        return $stmt->rowCount() > 0;
    }

    public function queuePendingDeliveries(): int
    {
        if (!$this->tableExists('notification_channels') || !$this->tableExists('notification_deliveries')) {
            return 0;
        }

        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO notification_deliveries (channel_id, alert_event_id, status)
            SELECT nc.id, ae.id, 'pending'
            FROM notification_channels nc
            INNER JOIN alert_events ae
                ON ae.id > nc.starts_after_event_id
               AND (
                    (ae.event_type = 'opened' AND nc.notify_on_open = 1)
                    OR (ae.event_type = 'resolved' AND nc.notify_on_resolve = 1)
               )
            WHERE nc.enabled = 1
              AND CASE ae.severity
                    WHEN 'critical' THEN 3
                    WHEN 'warning' THEN 2
                    ELSE 1
                  END >= CASE nc.minimum_severity
                    WHEN 'critical' THEN 3
                    WHEN 'warning' THEN 2
                    ELSE 1
                  END
        ");
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function getPendingDeliveries(int $maxAttempts, int $limit = 50): array
    {
        if (!$this->tableExists('notification_deliveries')) {
            return [];
        }

        $maxAttempts = max(1, $maxAttempts);
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare("
            SELECT
                nd.id AS delivery_id,
                nd.attempt_count,
                nc.id AS channel_id,
                nc.name AS channel_name,
                nc.channel_type,
                nc.endpoint_encrypted,
                ae.id AS event_id,
                ae.event_type,
                ae.severity,
                ae.message AS event_message,
                ae.created_at AS event_created_at,
                a.id AS alert_id,
                a.rule_key,
                a.status AS alert_status,
                a.title,
                a.message AS alert_message,
                ar.source,
                s.name AS server_name,
                s.hostname
            FROM notification_deliveries nd
            INNER JOIN notification_channels nc ON nc.id = nd.channel_id
            INNER JOIN alert_events ae ON ae.id = nd.alert_event_id
            INNER JOIN alerts a ON a.id = ae.alert_id
            LEFT JOIN alert_rules ar ON ar.rule_key = a.rule_key
            LEFT JOIN servers s ON s.id = a.server_id
            WHERE nc.enabled = 1
              AND (
                    nd.status IN ('pending', 'failed')
                    OR (
                        nd.status = 'processing'
                        AND nd.last_attempt_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    )
                  )
              AND nd.attempt_count < :max_attempts
            ORDER BY nd.id ASC
            LIMIT {$limit}
        ");
        $stmt->bindValue(':max_attempts', $maxAttempts, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function claimDelivery(int $deliveryId, int $maxAttempts): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE notification_deliveries
            SET status = 'processing',
                attempt_count = attempt_count + 1,
                last_attempt_at = NOW()
            WHERE id = :id
              AND attempt_count < :max_attempts
              AND (
                    status IN ('pending', 'failed')
                    OR (
                        status = 'processing'
                        AND last_attempt_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    )
                  )
        ");
        $stmt->execute([
            ':id' => $deliveryId,
            ':max_attempts' => max(1, $maxAttempts),
        ]);

        return $stmt->rowCount() === 1;
    }

    public function markSent(int $deliveryId, int $responseCode): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE notification_deliveries
            SET status = 'sent',
                response_code = :response_code,
                error_message = NULL,
                sent_at = NOW()
            WHERE id = :id
              AND status = 'processing'
        ");
        $stmt->execute([':response_code' => $responseCode, ':id' => $deliveryId]);
    }

    public function markFailed(int $deliveryId, ?int $responseCode, string $errorMessage): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE notification_deliveries
            SET status = 'failed',
                response_code = :response_code,
                error_message = :error_message
            WHERE id = :id
              AND status = 'processing'
        ");
        $stmt->execute([
            ':response_code' => $responseCode,
            ':error_message' => mb_substr($errorMessage, 0, 500),
            ':id' => $deliveryId,
        ]);
    }

    public function getRecentDeliveries(int $limit = 50): array
    {
        if (!$this->tableExists('notification_deliveries')) {
            return [];
        }

        $limit = max(1, min(200, $limit));

        return $this->pdo->query("
            SELECT nd.*, nc.name AS channel_name, nc.channel_type,
                   ae.event_type, ae.severity, a.title
            FROM notification_deliveries nd
            INNER JOIN notification_channels nc ON nc.id = nd.channel_id
            INNER JOIN alert_events ae ON ae.id = nd.alert_event_id
            INNER JOIN alerts a ON a.id = ae.alert_id
            ORDER BY nd.id DESC
            LIMIT {$limit}
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $stmt->execute([$tableName]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
