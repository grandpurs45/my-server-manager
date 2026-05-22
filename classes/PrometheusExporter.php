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
        ];

        foreach ($this->getServers() as $server) {
            $labels = $this->formatLabels([
                'server' => $server['name'] ?? '',
                'hostname' => $server['hostname'] ?? '',
            ]);

            $serverUp = ($server['status'] ?? '') === 'up' ? 1 : 0;
            $sshOk = ($server['ssh_status'] ?? '') === 'success' ? 1 : 0;

            $lines[] = "msm_server_up{{$labels}} {$serverUp}";
            $lines[] = "msm_ssh_ok{{$labels}} {$sshOk}";

            if ($server['latency'] !== null) {
                $lines[] = "msm_server_latency_ms{{$labels}} " . (int) $server['latency'];
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function getServers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT name, hostname, status, ssh_status, latency FROM servers ORDER BY name ASC'
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
