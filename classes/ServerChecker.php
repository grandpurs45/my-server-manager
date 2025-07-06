<?php
namespace MSM;
require_once __DIR__ . '/../includes/crypto.php';

use phpseclib3\Net\SSH2;

class ServerChecker
{
    private \PDO $pdo;
    private bool $withMetrics;

    public function __construct(\PDO $pdo, bool $withMetrics = true)
    {
        $this->pdo = $pdo;
        $this->withMetrics = $withMetrics;
    }

    public function run(): void
    {
        $stmt = $this->pdo->query("SELECT id, hostname, os, ssh_user, ssh_password, ssh_port, ssh_enabled FROM servers");
        $servers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $now = date('Y-m-d H:i:s');

        foreach ($servers as $server) {
            if (isset($server['ssh_enabled']) && !$server['ssh_enabled']) {
                echo "[{$server['hostname']}] SSH désactivé\n";

                // Mise à jour SSH KO si jamais activé avant
                $this->updateSshOk($server['id'], false);
                continue;
            }
            $ping = $this->getPingStats($server['hostname']);
            $status = $ping['status'];
            $latency = $ping['latency'];
            $availability = ($status === 'up') ? 1 : 0;

            $update = $this->pdo->prepare("UPDATE servers SET status = :status, last_check = :last_check, latency = :latency WHERE id = :id");
            $update->execute([
                ':status' => $status,
                ':last_check' => $now,
                ':latency' => $latency,
                ':id' => $server['id']
            ]);

            if ($this->withMetrics) {
                if ($latency !== null) {
                    $this->insertMetric($server['id'], 'ping', $latency, $now);
                }

                $this->insertMetric($server['id'], 'availability', $availability, $now);

                $diskUsage = $this->getDiskUsageViaSSH($server);
                if ($diskUsage !== null) {
                    $this->insertMetric($server['id'], 'disk', $diskUsage, $now);
                }
            }

            echo "[{$server['hostname']}] status=$status latency=" . ($latency ?? '-') . "\n";
        }
    }

    public function getPingStats(string $ip): array
    {
        if (!function_exists('exec')) return ['status' => 'down', 'latency' => null];

        $timeout = 1;
        $isWindows = stripos(PHP_OS, 'WIN') === 0;

        $pingCmd = $isWindows
            ? "ping -n 1 -w " . ($timeout * 1000) . " $ip"
            : "ping -c 1 -W $timeout $ip";

        exec($pingCmd, $output, $resultCode);

        if ($resultCode !== 0) {
            return ['status' => 'down', 'latency' => null];
        }

        $latency = null;
        foreach ($output as $line) {
            if ($isWindows && preg_match('/temps[=<]?\s*(\d+)\s*ms/i', $line, $matches)) {
                $latency = (int) $matches[1];
                break;
            } elseif (!$isWindows && preg_match('/time[=<]?(\d+\.?\d*)\s*ms/i', $line, $matches)) {
                $latency = round((float) $matches[1]);
                break;
            }
        }

        return ['status' => 'up', 'latency' => $latency];
    }

    private function insertMetric(int $serverId, string $type, float $value, string $datetime): void
    {
        $insert = $this->pdo->prepare("INSERT INTO server_metrics (server_id, type, value, measured_at)
                                       VALUES (:server_id, :type, :value, :measured_at)");
        $insert->execute([
            ':server_id' => $serverId,
            ':type' => $type,
            ':value' => $value,
            ':measured_at' => $datetime
        ]);
    }

    private function getDiskUsageViaSSH(array $server): ?float
    {
        if (empty($server['ssh_user']) || empty($server['ssh_password']) || empty($server['ssh_port'])) {
            return null;
        }

        try {
            $server['ssh_password'] = decrypt($server['ssh_password']);
            $ssh = new SSH2($server['hostname'], (int)$server['ssh_port']);
            if (!$ssh->login($server['ssh_user'], $server['ssh_password'])) {
                $this->updateSshOk($server['id'], false);
                return null;
            }

            $this->updateSshOk($server['id'], true);

            if ($server['os'] && stripos($server['os'], 'windows') !== false) {
                $output = $ssh->exec("wmic logicaldisk where \"DeviceID='C:'\" get FreeSpace,Size /format:csv");
                $lines = explode("\n", trim($output));
                if (count($lines) < 2) return null;
                $parts = str_getcsv($lines[1]);
                $free = (float) $parts[1];
                $total = (float) $parts[2];
            } else {
                $output = $ssh->exec('df -P / | tail -1');
                $cols = preg_split('/\s+/', trim($output));
                $usedPercent = rtrim($cols[4], '%');
                return (float) $usedPercent;
            }

            if ($total > 0) {
                $used = 100 - (($free / $total) * 100);
                return round($used, 1);
            }
        } catch (\Throwable $e) {
            $this->updateSshOk($server['id'], false);
            return null;
        }

        return null;
    }

    private function updateSshOk(int $id, bool $ok): void
    {
        $stmt = $this->pdo->prepare("UPDATE servers SET ssh_ok = :ok WHERE id = :id");
        $stmt->execute([
            ':ok' => $ok ? 1 : 0,
            ':id' => $id
        ]);
    }
}
