<?php
namespace MSM;

use PDO;

class HomeAssistantRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function saveResult(HomeAssistantCheckResult $result): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO home_assistant_checks (
                server_id,
                collector,
                status,
                installation_type,
                ha_version,
                ha_latest_version,
                ha_update_available,
                supervisor_version,
                supervisor_latest_version,
                supervisor_update_available,
                os_version,
                os_latest_version,
                os_update_available,
                host_os,
                kernel,
                checked_at,
                duration_ms,
                error_message,
                raw_summary
            ) VALUES (
                :server_id,
                :collector,
                :status,
                :installation_type,
                :ha_version,
                :ha_latest_version,
                :ha_update_available,
                :supervisor_version,
                :supervisor_latest_version,
                :supervisor_update_available,
                :os_version,
                :os_latest_version,
                :os_update_available,
                :host_os,
                :kernel,
                :checked_at,
                :duration_ms,
                :error_message,
                :raw_summary
            )
        ");

        $stmt->execute([
            ':server_id' => $result->serverId,
            ':collector' => $result->collector,
            ':status' => $result->status,
            ':installation_type' => $result->installationType,
            ':ha_version' => $result->haVersion,
            ':ha_latest_version' => $result->haLatestVersion,
            ':ha_update_available' => $this->nullableBool($result->haUpdateAvailable),
            ':supervisor_version' => $result->supervisorVersion,
            ':supervisor_latest_version' => $result->supervisorLatestVersion,
            ':supervisor_update_available' => $this->nullableBool($result->supervisorUpdateAvailable),
            ':os_version' => $result->osVersion,
            ':os_latest_version' => $result->osLatestVersion,
            ':os_update_available' => $this->nullableBool($result->osUpdateAvailable),
            ':host_os' => $result->hostOs,
            ':kernel' => $result->kernel,
            ':checked_at' => $result->checkedAt,
            ':duration_ms' => $result->durationMs,
            ':error_message' => $result->errorMessage,
            ':raw_summary' => $result->rawSummary !== null
                ? json_encode($result->rawSummary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getLatestForServer(int $serverId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM home_assistant_checks
            WHERE server_id = :server_id
            ORDER BY checked_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([':server_id' => $serverId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    private function nullableBool(?bool $value): ?int
    {
        return $value === null ? null : ($value ? 1 : 0);
    }
}
