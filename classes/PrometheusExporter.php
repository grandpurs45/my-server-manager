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
            '# HELP msm_security_check_success Last known MSM security check success status for the server.',
            '# TYPE msm_security_check_success gauge',
            '# HELP msm_security_check_status Last known security check status. Value is always 1 for the current status label.',
            '# TYPE msm_security_check_status gauge',
            '# HELP msm_security_open_ports Last known open ports count from MSM security checks.',
            '# TYPE msm_security_open_ports gauge',
            '# HELP msm_security_exposed_ports Last known publicly exposed ports count from MSM security checks.',
            '# TYPE msm_security_exposed_ports gauge',
            '# HELP msm_security_firewall_enabled Last known firewall enabled status from MSM security checks.',
            '# TYPE msm_security_firewall_enabled gauge',
            '# HELP msm_security_last_check_timestamp Last known security check timestamp as Unix epoch seconds.',
            '# TYPE msm_security_last_check_timestamp gauge',
            '# HELP msm_hardware_temperature_celsius Last known hardware temperature reported by MSM.',
            '# TYPE msm_hardware_temperature_celsius gauge',
            '# HELP msm_hardware_health_check_status Last known hardware health check status. Value is always 1 for the current status label.',
            '# TYPE msm_hardware_health_check_status gauge',
            '# HELP msm_hardware_health_last_check_timestamp Last known hardware health check timestamp as Unix epoch seconds.',
            '# TYPE msm_hardware_health_last_check_timestamp gauge',
            '# HELP msm_hardware_smart_passed Last known SMART overall health status for a physical disk.',
            '# TYPE msm_hardware_smart_passed gauge',
            '# HELP msm_hardware_disk_temperature_celsius Last known SMART disk temperature.',
            '# TYPE msm_hardware_disk_temperature_celsius gauge',
            '# HELP msm_hardware_disk_power_on_hours Last known SMART disk power-on hours.',
            '# TYPE msm_hardware_disk_power_on_hours counter',
            '# HELP msm_hardware_disk_percentage_used Last known SMART/NVMe disk endurance percentage used.',
            '# TYPE msm_hardware_disk_percentage_used gauge',
            '# HELP msm_hardware_disk_media_errors Last known SMART/NVMe media errors count.',
            '# TYPE msm_hardware_disk_media_errors counter',
            '# HELP msm_alerts_active Active MSM alerts count grouped by severity.',
            '# TYPE msm_alerts_active gauge',
            '# HELP msm_alert_active Active MSM alert. Value is always 1 for each active alert.',
            '# TYPE msm_alert_active gauge',
        ];

        $diskUsages = $this->getLatestMetricValues('disk');
        $latestPatchChecks = $this->getLatestPatchChecks();
        $latestOsLifecycleChecks = $this->getLatestOsLifecycleChecks();
        $latestSecurityChecks = $this->getLatestSecurityChecks();
        $latestHardwareChecks = $this->getLatestHardwareChecks();
        $activeAlerts = $this->getActiveAlerts();
        $activeAlertCounts = ['critical' => 0, 'warning' => 0, 'info' => 0];

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

            if (isset($latestSecurityChecks[$serverId])) {
                $securityCheck = $latestSecurityChecks[$serverId];
                $securityLabels = $this->formatLabels($baseLabels);
                $securityStatusLabels = $this->formatLabels($baseLabels + [
                    'status' => $securityCheck['status'] ?: 'unknown',
                ]);

                $firewallEnabled = ($securityCheck['firewall_status'] ?? null) === 'actif' ? 1 : 0;
                $securitySuccess = ($securityCheck['status'] ?? null) !== 'error' ? 1 : 0;

                $lines[] = "msm_security_check_success{{$securityLabels}} {$securitySuccess}";
                $lines[] = "msm_security_check_status{{$securityStatusLabels}} 1";
                $lines[] = "msm_security_open_ports{{$securityLabels}} " . (int) $securityCheck['open_ports_count'];
                $lines[] = "msm_security_exposed_ports{{$securityLabels}} " . (int) $securityCheck['exposed_ports_count'];
                $lines[] = "msm_security_firewall_enabled{{$securityLabels}} {$firewallEnabled}";

                if ($securityCheck['checked_at_timestamp'] !== null) {
                    $lines[] = "msm_security_last_check_timestamp{{$securityLabels}} " . (int) $securityCheck['checked_at_timestamp'];
                }
            }

            if (isset($latestHardwareChecks[$serverId])) {
                $hardwareCheck = $latestHardwareChecks[$serverId];
                $hardwareStatusLabels = $this->formatLabels($baseLabels + [
                    'hardware_profile' => $server['hardware_profile'] ?? 'unknown',
                    'collector' => $hardwareCheck['collector'] ?: 'unknown',
                    'status' => $hardwareCheck['status'] ?: 'unknown',
                ]);
                $lines[] = "msm_hardware_health_check_status{{$hardwareStatusLabels}} 1";

                if ($hardwareCheck['checked_at_timestamp'] !== null) {
                    $hardwareLabels = $this->formatLabels($baseLabels + [
                        'hardware_profile' => $server['hardware_profile'] ?? 'unknown',
                        'collector' => $hardwareCheck['collector'] ?: 'unknown',
                    ]);
                    $lines[] = "msm_hardware_health_last_check_timestamp{{$hardwareLabels}} " . (int) $hardwareCheck['checked_at_timestamp'];
                }

                foreach ($hardwareCheck['temperatures'] as $temperature) {
                    $temperatureLabels = $this->formatLabels($baseLabels + [
                        'hardware_profile' => $server['hardware_profile'] ?? 'unknown',
                        'collector' => $hardwareCheck['collector'] ?: 'unknown',
                        'sensor' => $temperature['sensor_key'] ?? 'unknown',
                        'sensor_label' => $temperature['sensor_label'] ?? 'Sonde',
                        'sensor_type' => $temperature['sensor_type'] ?? 'other',
                    ]);
                    $lines[] = "msm_hardware_temperature_celsius{{$temperatureLabels}} "
                        . $this->formatFloat((float) $temperature['temperature_celsius']);
                }

                foreach ($hardwareCheck['smart_disks'] as $disk) {
                    $diskLabels = $this->formatLabels($baseLabels + [
                        'hardware_profile' => $server['hardware_profile'] ?? 'unknown',
                        'device' => $disk['device_name'] ?? 'unknown',
                        'protocol' => $disk['protocol'] ?? 'unknown',
                        'model' => $disk['model_name'] ?? 'unknown',
                    ]);

                    if ($disk['smart_passed'] !== null) {
                        $lines[] = "msm_hardware_smart_passed{{$diskLabels}} " . ((int) $disk['smart_passed'] === 1 ? 1 : 0);
                    }
                    if ($disk['temperature_celsius'] !== null) {
                        $lines[] = "msm_hardware_disk_temperature_celsius{{$diskLabels}} "
                            . $this->formatFloat((float) $disk['temperature_celsius']);
                    }
                    if ($disk['power_on_hours'] !== null) {
                        $lines[] = "msm_hardware_disk_power_on_hours{{$diskLabels}} " . (int) $disk['power_on_hours'];
                    }
                    if ($disk['percentage_used'] !== null) {
                        $lines[] = "msm_hardware_disk_percentage_used{{$diskLabels}} "
                            . $this->formatFloat((float) $disk['percentage_used']);
                    }
                    if ($disk['media_errors'] !== null) {
                        $lines[] = "msm_hardware_disk_media_errors{{$diskLabels}} " . (int) $disk['media_errors'];
                    }
                }
            }
        }

        foreach ($activeAlerts as $alert) {
            $severity = $alert['severity'] ?: 'info';
            if (!isset($activeAlertCounts[$severity])) {
                $activeAlertCounts[$severity] = 0;
            }
            $activeAlertCounts[$severity]++;

            $alertLabels = $this->formatLabels([
                'server' => $alert['server_name'] ?? 'global',
                'hostname' => $alert['hostname'] ?? '',
                'type' => $alert['target_type'] ?? 'other',
                'rule' => $alert['rule_key'] ?? 'unknown',
                'severity' => $severity,
            ]);
            $lines[] = "msm_alert_active{{$alertLabels}} 1";
        }

        foreach ($activeAlertCounts as $severity => $count) {
            $alertCountLabels = $this->formatLabels(['severity' => $severity]);
            $lines[] = "msm_alerts_active{{$alertCountLabels}} " . (int) $count;
        }

        return implode("\n", $lines) . "\n";
    }

    private function getServers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, hostname, target_type, hardware_profile, status, ssh_status, latency, last_check, UNIX_TIMESTAMP(last_check) AS last_check_timestamp
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

    private function getLatestSecurityChecks(): array
    {
        if (!$this->tableExists('security_checks')) {
            return [];
        }

        $stmt = $this->pdo->query(
            'SELECT sc.server_id,
                    sc.status,
                    sc.open_ports_count,
                    sc.exposed_ports_count,
                    sc.firewall_status,
                    UNIX_TIMESTAMP(sc.checked_at) AS checked_at_timestamp
             FROM security_checks sc
             INNER JOIN (
                 SELECT server_id, MAX(checked_at) AS checked_at
                 FROM security_checks
                 GROUP BY server_id
             ) latest
                ON latest.server_id = sc.server_id
               AND latest.checked_at = sc.checked_at'
        );

        $checks = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $checks[(int) $row['server_id']] = $row;
        }

        return $checks;
    }

    private function getLatestHardwareChecks(): array
    {
        if (!$this->tableExists('hardware_health_checks') || !$this->tableExists('hardware_temperature_readings')) {
            return [];
        }

        $stmt = $this->pdo->query(
            'SELECT hc.id,
                    hc.server_id,
                    hc.collector,
                    hc.status,
                    UNIX_TIMESTAMP(hc.checked_at) AS checked_at_timestamp
             FROM hardware_health_checks hc
             INNER JOIN (
                 SELECT server_id, MAX(checked_at) AS checked_at
                 FROM hardware_health_checks
                 GROUP BY server_id
             ) latest
                ON latest.server_id = hc.server_id
               AND latest.checked_at = hc.checked_at'
        );

        $checks = [];
        $temperatureStmt = $this->pdo->prepare(
            'SELECT sensor_key, sensor_label, sensor_type, temperature_celsius
             FROM hardware_temperature_readings
             WHERE hardware_check_id = :hardware_check_id
             ORDER BY id ASC'
        );
        $smartDiskStmt = $this->tableExists('hardware_smart_disks')
            ? $this->pdo->prepare(
                'SELECT device_name, protocol, model_name, smart_passed, temperature_celsius,
                        power_on_hours, percentage_used, media_errors
                 FROM hardware_smart_disks
                 WHERE hardware_check_id = :hardware_check_id
                 ORDER BY id ASC'
            )
            : null;

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $temperatureStmt->execute([':hardware_check_id' => (int) $row['id']]);
            $row['temperatures'] = $temperatureStmt->fetchAll(\PDO::FETCH_ASSOC);
            $row['smart_disks'] = [];
            if ($smartDiskStmt !== null) {
                $smartDiskStmt->execute([':hardware_check_id' => (int) $row['id']]);
                $row['smart_disks'] = $smartDiskStmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            $checks[(int) $row['server_id']] = $row;
        }

        return $checks;
    }

    private function getActiveAlerts(): array
    {
        if (!$this->tableExists('alerts')) {
            return [];
        }

        $stmt = $this->pdo->query(
            "SELECT
                a.rule_key,
                a.severity,
                s.name AS server_name,
                s.hostname,
                s.target_type
             FROM alerts a
             LEFT JOIN servers s ON s.id = a.server_id
             WHERE a.status = 'active'
               AND a.acknowledged_at IS NULL
               AND a.ignored_at IS NULL
             ORDER BY a.id ASC"
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
