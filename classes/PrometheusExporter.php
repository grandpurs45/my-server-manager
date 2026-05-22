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
        ];

        $diskUsages = $this->getLatestMetricValues('disk');

        foreach ($this->getServers() as $server) {
            $labels = $this->formatLabels([
                'server' => $server['name'] ?? '',
                'hostname' => $server['hostname'] ?? '',
            ]);

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
        }

        return implode("\n", $lines) . "\n";
    }

    private function getServers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, hostname, status, ssh_status, latency, last_check, UNIX_TIMESTAMP(last_check) AS last_check_timestamp
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
