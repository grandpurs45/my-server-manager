<?php
namespace MSM;

use PDO;

class PatchStatusRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getOverview(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                s.id,
                s.name,
                s.hostname,
                s.target_type,
                s.environment,
                s.criticality,
                s.patch_management_enabled,
                pc.collector,
                pc.status AS patch_status,
                pc.normal_updates_count,
                pc.security_updates_count,
                pc.reboot_required,
                pc.checked_at,
                pc.error_message
            FROM servers s
            LEFT JOIN patch_checks pc
                ON pc.id = (
                    SELECT pc2.id
                    FROM patch_checks pc2
                    WHERE pc2.server_id = s.id
                    ORDER BY pc2.checked_at DESC, pc2.id DESC
                    LIMIT 1
                )
            WHERE s.patch_management_enabled = 1
            ORDER BY s.name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countDisabledTargets(): int
    {
        return (int) $this->pdo
            ->query("SELECT COUNT(*) FROM servers WHERE patch_management_enabled = 0")
            ->fetchColumn();
    }

    public function getLatestForServer(int $serverId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM patch_checks
            WHERE server_id = :server_id
            ORDER BY checked_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([':server_id' => $serverId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function getUpdatesForCheck(int $patchCheckId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                update_type,
                package_name,
                installed_version,
                candidate_version,
                source,
                severity
            FROM patch_updates
            WHERE patch_check_id = :patch_check_id
            ORDER BY
                CASE update_type WHEN 'security' THEN 0 ELSE 1 END,
                package_name ASC
        ");
        $stmt->execute([':patch_check_id' => $patchCheckId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveResult(PatchCheckResult $result): int
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO patch_checks (
                    server_id,
                    collector,
                    status,
                    normal_updates_count,
                    security_updates_count,
                    reboot_required,
                    checked_at,
                    duration_ms,
                    error_message
                ) VALUES (
                    :server_id,
                    :collector,
                    :status,
                    :normal_updates_count,
                    :security_updates_count,
                    :reboot_required,
                    :checked_at,
                    :duration_ms,
                    :error_message
                )
            ");
            $stmt->execute([
                ':server_id' => $result->serverId,
                ':collector' => $result->collector,
                ':status' => $result->status,
                ':normal_updates_count' => $result->normalUpdatesCount,
                ':security_updates_count' => $result->securityUpdatesCount,
                ':reboot_required' => $result->rebootRequired ? 1 : 0,
                ':checked_at' => $result->checkedAt,
                ':duration_ms' => $result->durationMs,
                ':error_message' => $result->errorMessage,
            ]);

            $checkId = (int) $this->pdo->lastInsertId();

            $updateStmt = $this->pdo->prepare("
                INSERT INTO patch_updates (
                    patch_check_id,
                    update_type,
                    package_name,
                    installed_version,
                    candidate_version,
                    source,
                    severity
                ) VALUES (
                    :patch_check_id,
                    :update_type,
                    :package_name,
                    :installed_version,
                    :candidate_version,
                    :source,
                    :severity
                )
            ");

            foreach ($result->updates as $update) {
                $updateStmt->execute([
                    ':patch_check_id' => $checkId,
                    ':update_type' => $update['type'] ?? 'normal',
                    ':package_name' => $update['package'] ?? '',
                    ':installed_version' => $update['installed_version'] ?? null,
                    ':candidate_version' => $update['candidate_version'] ?? null,
                    ':source' => $update['source'] ?? null,
                    ':severity' => $update['severity'] ?? null,
                ]);
            }

            $this->pdo->commit();
            return $checkId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
