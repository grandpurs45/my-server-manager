<?php
namespace MSM;

class PrometheusExporter
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function render(): string
    {
        $lines = [
            '# HELP msm_server_up Last known server reachability status from MSM.',
            '# TYPE msm_server_up gauge',
            '# HELP msm_ssh_ok Last known SSH connection status from MSM.',
            '# TYPE msm_ssh_ok gauge',
            '# HELP msm_server_latency_ms Last known ping latency in milliseconds.',
            '# TYPE msm_server_latency_ms gauge',
            '# HELP msm_server_disk_usage_percent Last known root disk usage percentage from MSM.',
            '# TYPE msm_server_disk_usage_percent gauge',
            '# HELP msm_server_last_check_timestamp Last known server check timestamp as Unix epoch seconds.',
            '# TYPE msm_server_last_check_timestamp gauge',
            '# HELP msm_check_success Last known MSM check success status for the server.',
            '# TYPE msm_check_success gauge',
            '# HELP msm_updates_available Last known available package updates from MSM Patch Management.',
            '# TYPE msm_updates_available gauge',
            '# HELP msm_reboot_required Last known reboot required status from MSM Patch Management.',
            '# TYPE msm_reboot_required gauge',
            '# HELP msm_patch_check_status Last known Patch Management status. Value is always 1 for the current status label.',
            '# TYPE msm_patch_check_status gauge',
            '# HELP msm_patch_check_timestamp Last known Patch Management check timestamp as Unix epoch seconds.',
            '# TYPE msm_patch_check_timestamp gauge',
            '# HELP msm_os_support_status Last known OS lifecycle support status. Value is always 1 for the current support_status label.',
            '# TYPE msm_os_support_status gauge',
            '# HELP msm_os_upgrade_available Last known OS upgrade availability from MSM lifecycle references.',
            '# TYPE msm_os_upgrade_available gauge',
            '# HELP msm_os_support_end_timestamp Known OS support end date as Unix epoch seconds.',
            '# TYPE msm_os_support_end_timestamp gauge',
            '# HELP msm_os_lifecycle_check_timestamp Last known OS lifecycle check timestamp as Unix epoch seconds.',
            '# TYPE msm_os_lifecycle_check_timestamp gauge',
        ];

        $diskUsages = $this->getLatestMetricValues('disk');
        $latestPatchChecks = $this->getLatestPatchChecks();
        $latestOsLifecycleChecks = $this->getLatestOsLifecycleChecks();

        foreach ($this->getServers() as $server) {
            $baseLabels = [
                'server' => $server['name'] ?? '',
                'hostname' => $server['hostname'] ?? '',
                'type' => $server['target_type'] ?? 'other',
            ];
            $labels = $this->formatLabels($baseLabels);

            $serverUp = ($server['status'] ?? '') === 'up' ? 1 : 0;
            $sshOk = ($server['ssh_status'] ?? '') === 'success' ? 1 : 0;
            $checkSuccess = $server['last_check'] !== null ? 1 : 0;

            $lines[] = "msm_server_up{{$labels}} {$serverUp}";
            $lines[] = "msm_ssh_ok{{$labels}} {$sshOk}";
            $lines[] = "msm_check_success{{$labels}} {$checkSuccess}";

            if ($server['latency'] !== null) {
                $lines[] = "msm_server_latency_ms{{$labels}} " . (int) $server['latency'];
            }

            if ($server['last_check_timestamp'] !== null) {
                $lines[] = "msm_server_last_check_timestamp{{$labels}} " . (int) $server['last_check_timestamp'];
            }

            $serverId = (int) $server['id'];
            if (isset($diskUsages[$serverId])) {
                $lines[] = "msm_server_disk_usage_percent{{$labels}} " . $this->formatFloat($diskUsages[$serverId]);
            }

            if (isset($latestPatchChecks[$serverId])) {
                $patchCheck = $latestPatchChecks[$serverId];
                $patchLabels = $this->formatLabels($baseLabels + [
                    'collector' => $patchCheck['collector'] ?: 'unknown',
                ]);
                $patchStatusLabels = $this->formatLabels($baseLabels + [
                    'collector' => $patchCheck['collector'] ?: 'unknown',
                    'status' => $patchCheck['status'] ?: 'unknown',
                ]);

                $securityUpdateLabels = $this->formatLabels($baseLabels + ['update_type' => 'security']);
                $normalUpdateLabels = $this->formatLabels($baseLabels + ['update_type' => 'normal']);

                $lines[] = "msm_updates_available{{$securityUpdateLabels}} " . (int) $patchCheck['security_updates_count'];
                $lines[] = "msm_updates_available{{$normalUpdateLabels}} " . (int) $patchCheck['normal_updates_count'];
                $lines[] = "msm_reboot_required{{$patchLabels}} " . (!empty($patchCheck['reboot_required']) ? 1 : 0);
                $lines[] = "msm_patch_check_status{{$patchStatusLabels}} 1";

                if ($patchCheck['checked_at_timestamp'] !== null) {
                    $lines[] = "msm_patch_check_timestamp{{$patchLabels}} " . (int) $patchCheck['checked_at_timestamp'];
                }
            }

            if (isset($latestOsLifecycleChecks[$serverId])) {
                $osCheck = $latestOsLifecycleChecks[$serverId];
                $osLabels = $this->formatLabels($baseLabels + [
                    'os_family' => $osCheck['os_family'] ?: 'unknown',
                    'os_version' => $osCheck['os_version'] ?: 'unknown',
                ]);
                $osStatusLabels = $this->formatLabels($baseLabels + [
                    'os_family' => $osCheck['os_family'] ?: 'unknown',
                    'os_version' => $osCheck['os_version'] ?: 'unknown',
                    'support_status' => $osCheck['support_status'] ?: 'unknown',
                ]);

                $lines[] = "msm_os_support_status{{$osStatusLabels}} 1";
                $lines[] = "msm_os_upgrade_available{{$osLabels}} " . (!empty($osCheck['upgrade_available']) ? 1 : 0);

                if ($osCheck['support_ends_at_timestamp'] !== null) {
                    $lines[] = "msm_os_support_end_timestamp{{$osLabels}} " . (int) $osCheck['support_ends_at_timestamp'];
                }

                if ($osCheck['checked_at_timestamp'] !== null) {
                    $lines[] = "msm_os_lifecycle_check_timestamp{{$osLabels}} " . (int) $osCheck['checked_at_timestamp'];
                }
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function getServers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, hostname, target_type, status, ssh_status, latency, last_check, UNIX_TIMESTAMP(last_check) AS last_check_timestamp
             FROM servers
             ORDER BY name ASC'
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getLatestMetricValues(string $type): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sm.server_id, sm.value
             FROM server_metrics sm
             INNER JOIN (
                 SELECT server_id, MAX(measured_at) AS measured_at
                 FROM server_metrics
                 WHERE type = :type
                 GROUP BY server_id
             ) latest
                ON latest.server_id = sm.server_id
               AND latest.measured_at = sm.measured_at
             WHERE sm.type = :type'
        );
        $stmt->execute([':type' => $type]);

        $values = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $values[(int) $row['server_id']] = (float) $row['value'];
        }

        return $values;
    }

    private function getLatestPatchChecks(): array
    {
        if (!$this->tableExists('patch_checks')) {
            return [];
        }

        $stmt = $this->pdo->query(
            'SELECT pc.server_id,
                    pc.collector,
                    pc.status,
                    pc.normal_updates_count,
                    pc.security_updates_count,
                    pc.reboot_required,
                    UNIX_TIMESTAMP(pc.checked_at) AS checked_at_timestamp
             FROM patch_checks pc
             INNER JOIN (
                 SELECT server_id, MAX(checked_at) AS checked_at
                 FROM patch_checks
                 GROUP BY server_id
             ) latest
                ON latest.server_id = pc.server_id
               AND latest.checked_at = pc.checked_at'
        );

        $checks = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $checks[(int) $row['server_id']] = $row;
        }

        return $checks;
    }

    private function getLatestOsLifecycleChecks(): array
    {
        if (!$this->tableExists('os_lifecycle_checks')) {
            return [];
        }

        $stmt = $this->pdo->query(
            'SELECT olc.server_id,
                    olc.os_family,
                    olc.os_version,
                    olc.support_status,
                    olc.upgrade_available,
                    UNIX_TIMESTAMP(olc.support_ends_at) AS support_ends_at_timestamp,
                    UNIX_TIMESTAMP(olc.checked_at) AS checked_at_timestamp
             FROM os_lifecycle_checks olc
             INNER JOIN (
                 SELECT server_id, MAX(checked_at) AS checked_at
                 FROM os_lifecycle_checks
                 GROUP BY server_id
             ) latest
                ON latest.server_id = olc.server_id
               AND latest.checked_at = olc.checked_at'
        );

        $checks = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $checks[(int) $row['server_id']] = $row;
        }

        return $checks;
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

    private function formatFloat(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }

    private function formatLabels(array $labels): string
    {
        $formatted = [];

        foreach ($labels as $name => $value) {
            $formatted[] = $name . '="' . $this->escapeLabelValue((string) $value) . '"';
        }

        return implode(',', $formatted);
    }

    private function escapeLabelValue(string $value): string
    {
        return str_replace(
            ["\\", "\n", '"'],
            ["\\\\", "\\n", '\\"'],
            $value
        );
    }
}
