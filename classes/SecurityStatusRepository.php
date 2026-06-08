<?php
namespace MSM;

use PDO;

class SecurityStatusRepository
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
                s.os,
                s.status AS server_status,
                s.ssh_status,
                s.security_enabled,
                sc.status AS security_status,
                sc.open_ports_count,
                sc.exposed_ports_count,
                sc.firewall_status,
                sc.checked_at,
                sc.error_message
            FROM servers s
            LEFT JOIN security_checks sc
                ON sc.id = (
                    SELECT sc2.id
                    FROM security_checks sc2
                    WHERE sc2.server_id = s.id
                    ORDER BY sc2.checked_at DESC, sc2.id DESC
                    LIMIT 1
                )
            WHERE s.security_enabled = 1
            ORDER BY s.name ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countDisabledTargets(): int
    {
        return (int) $this->pdo
            ->query("SELECT COUNT(*) FROM servers WHERE security_enabled = 0")
            ->fetchColumn();
    }

    public function getLatestForServer(int $serverId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM security_checks
            WHERE server_id = :server_id
            ORDER BY checked_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([':server_id' => $serverId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function getPortsForCheck(int $securityCheckId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT protocol, address, port, exposure
            FROM security_open_ports
            WHERE security_check_id = :security_check_id
            ORDER BY
                CASE exposure WHEN 'public' THEN 0 WHEN 'local' THEN 1 ELSE 2 END,
                port ASC,
                protocol ASC
        ");
        $stmt->execute([':security_check_id' => $securityCheckId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveResult(SecurityCheckResult $result): int
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO security_checks (
                    server_id,
                    status,
                    open_ports_count,
                    exposed_ports_count,
                    firewall_status,
                    checked_at,
                    duration_ms,
                    error_message
                ) VALUES (
                    :server_id,
                    :status,
                    :open_ports_count,
                    :exposed_ports_count,
                    :firewall_status,
                    :checked_at,
                    :duration_ms,
                    :error_message
                )
            ");
            $stmt->execute([
                ':server_id' => $result->serverId,
                ':status' => $result->status,
                ':open_ports_count' => count($result->openPorts),
                ':exposed_ports_count' => $result->exposedPortsCount(),
                ':firewall_status' => $result->firewallStatus,
                ':checked_at' => $result->checkedAt,
                ':duration_ms' => $result->durationMs,
                ':error_message' => $result->errorMessage,
            ]);

            $checkId = (int) $this->pdo->lastInsertId();
            $portStmt = $this->pdo->prepare("
                INSERT INTO security_open_ports (
                    security_check_id,
                    protocol,
                    address,
                    port,
                    exposure
                ) VALUES (
                    :security_check_id,
                    :protocol,
                    :address,
                    :port,
                    :exposure
                )
            ");

            foreach ($result->openPorts as $port) {
                $portStmt->execute([
                    ':security_check_id' => $checkId,
                    ':protocol' => $port['protocol'] ?? 'unknown',
                    ':address' => $port['address'] ?? '',
                    ':port' => (int) ($port['port'] ?? 0),
                    ':exposure' => $port['exposure'] ?? 'unknown',
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
