<?php
namespace MSM;

require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/network.php';

use phpseclib3\Net\SSH2;

class ServerChecker
{
    private \PDO $pdo;
    private bool $withMetrics;
    private ServerCheckHistoryRepository $history;
    private int $pingPacketCount;
    private int $pingTimeoutSeconds;

    public function __construct(\PDO $pdo, bool $withMetrics = true)
    {
        $this->pdo = $pdo;
        $this->withMetrics = $withMetrics;
        $this->history = new ServerCheckHistoryRepository($pdo);
        $settings = new SettingsManager($pdo);
        $this->pingPacketCount = max(1, min(10, (int) ($settings->get('supervision', 'ping_packet_count') ?? 4)));
        $this->pingTimeoutSeconds = max(1, min(10, (int) ($settings->get('supervision', 'ping_timeout_seconds') ?? 1)));
    }

    public function run(): void
    {
        $stmt = $this->pdo->query("SELECT id, hostname, os, status, ssh_user, ssh_password, ssh_port, ssh_enabled, ssh_status FROM servers");
        $servers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $now = date('Y-m-d H:i:s');

        foreach ($servers as $server) {
            $this->checkServer($server, $now);
        }
    }

    public function runForServerId(int $serverId): void
    {
        $stmt = $this->pdo->prepare("SELECT id, hostname, os, status, ssh_user, ssh_password, ssh_port, ssh_enabled, ssh_status FROM servers WHERE id = :id");
        $stmt->execute([':id' => $serverId]);
        $server = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$server) {
            throw new \RuntimeException('Cible introuvable.');
        }

        $this->checkServer($server, date('Y-m-d H:i:s'));
    }

    public function getPingStats(string $ip): array
    {
        if (!function_exists('exec')) {
            return $this->emptyPingStats('down');
        }

        $pingCmd = \msmBuildPingCommand($ip, $this->pingTimeoutSeconds, $this->pingPacketCount);
        if ($pingCmd === null) {
            return $this->emptyPingStats('down');
        }

        $isWindows = stripos(PHP_OS, 'WIN') === 0;

        exec($pingCmd, $output, $resultCode);

        $latencies = [];
        $packetsSent = $this->pingPacketCount;
        $packetsReceived = null;
        $packetLossPercent = null;
        $summaryLatencyMin = null;
        $summaryLatencyAvg = null;
        $summaryLatencyMax = null;

        foreach ($output as $line) {
            if ($isWindows && preg_match('/(?:temps|time)[=<]?\s*(\d+)\s*ms/i', $line, $matches)) {
                $latencies[] = (int) $matches[1];
                continue;
            }

            if (!$isWindows && preg_match('/(?:time|temps)[=<]?(\d+\.?\d*)\s*ms/i', $line, $matches)) {
                $latencies[] = round((float) $matches[1]);
                continue;
            }

            if ($isWindows && preg_match('/(?:Sent|envoy[^\d]*)\s*=\s*(\d+).*?(?:Received|re[çc]us?)\s*=\s*(\d+).*?\((?:perte\s*)?(\d+)\s*%/i', $line, $matches)) {
                $packetsSent = (int) $matches[1];
                $packetsReceived = (int) $matches[2];
                $packetLossPercent = (float) $matches[3];
                continue;
            }

            if (!$isWindows && preg_match('/(\d+)\s+packets transmitted,\s+(\d+)\s+(?:packets\s+)?received,.*?(\d+(?:\.\d+)?)%\s+packet loss/i', $line, $matches)) {
                $packetsSent = (int) $matches[1];
                $packetsReceived = (int) $matches[2];
                $packetLossPercent = (float) $matches[3];
                continue;
            }

            if ($isWindows && preg_match('/(?:Minimum|Minim(?:um|ale?))\s*=\s*(\d+)\s*ms.*?(?:Maximum|Maxim(?:um|ale?))\s*=\s*(\d+)\s*ms.*?(?:Average|Moyenne)\s*=\s*(\d+)\s*ms/i', $line, $matches)) {
                $summaryLatencyMin = (int) $matches[1];
                $summaryLatencyMax = (int) $matches[2];
                $summaryLatencyAvg = (int) $matches[3];
                continue;
            }

            if (!$isWindows && preg_match('/(?:rtt|round-trip).*?=\s*(\d+\.?\d*)\/(\d+\.?\d*)\/(\d+\.?\d*)\//i', $line, $matches)) {
                $latencies = [
                    round((float) $matches[1]),
                    round((float) $matches[2]),
                    round((float) $matches[3]),
                ];
            }
        }

        if ($packetsReceived === null) {
            $packetsReceived = count($latencies);
        }

        if ($packetLossPercent === null && $packetsSent > 0) {
            $packetLossPercent = round((($packetsSent - $packetsReceived) / $packetsSent) * 100, 2);
        }

        $latencyMin = $summaryLatencyMin ?? ($latencies !== [] ? min($latencies) : null);
        $latencyMax = $summaryLatencyMax ?? ($latencies !== [] ? max($latencies) : null);
        $latencyAvg = $summaryLatencyAvg ?? ($latencies !== [] ? (int) round(array_sum($latencies) / count($latencies)) : null);

        return [
            'status' => $packetsReceived > 0 || $resultCode === 0 ? 'up' : 'down',
            'latency' => $latencyAvg,
            'latency_min' => $latencyMin,
            'latency_max' => $latencyMax,
            'packets_sent' => $packetsSent,
            'packets_received' => $packetsReceived,
            'packet_loss_percent' => $packetLossPercent,
        ];
    }

    private function checkServer(array $server, string $now): void
    {
        $ping = $this->getPingStats($server['hostname']);
        $status = $ping['status'];
        $latency = $ping['latency'];
        $availability = ($status === 'up') ? 1 : 0;

        $this->history->recordChange(
            (int) $server['id'],
            'ping_status',
            $server['status'] ?? null,
            $status,
            'Statut ping passe de ' . ($server['status'] ?? 'inconnu') . ' a ' . $status . '.',
            $now
        );

        $update = $this->pdo->prepare("
            UPDATE servers
            SET status = :status,
                last_check = :last_check,
                latency = :latency,
                ping_packets_sent = :ping_packets_sent,
                ping_packets_received = :ping_packets_received,
                ping_loss_percent = :ping_loss_percent,
                latency_min_ms = :latency_min_ms,
                latency_max_ms = :latency_max_ms
            WHERE id = :id
        ");
        $update->execute([
            ':status' => $status,
            ':last_check' => $now,
            ':latency' => $latency,
            ':ping_packets_sent' => $ping['packets_sent'],
            ':ping_packets_received' => $ping['packets_received'],
            ':ping_loss_percent' => $ping['packet_loss_percent'],
            ':latency_min_ms' => $ping['latency_min'],
            ':latency_max_ms' => $ping['latency_max'],
            ':id' => $server['id'],
        ]);

        if ($this->withMetrics) {
            if ($latency !== null) {
                $this->insertMetric((int) $server['id'], 'ping', $latency, $now);
            }

            $this->insertMetric((int) $server['id'], 'availability', $availability, $now);
            if ($ping['packet_loss_percent'] !== null) {
                $this->insertMetric((int) $server['id'], 'ping_loss', (float) $ping['packet_loss_percent'], $now);
            }

            $diskUsage = $this->getDiskUsageViaSSH($server);
            if ($diskUsage !== null) {
                $this->insertMetric((int) $server['id'], 'disk', $diskUsage, $now);
            }
        }

        echo "[{$server['hostname']}] status=$status latency=" . ($latency ?? '-')
            . ' loss=' . ($ping['packet_loss_percent'] ?? '-')
            . '% packets=' . ($ping['packets_received'] ?? 0) . '/' . ($ping['packets_sent'] ?? 0) . "\n";

        if (isset($server['ssh_enabled']) && !$server['ssh_enabled']) {
            echo "[{$server['hostname']}] SSH desactive\n";
            $this->updateSshOk((int) $server['id'], false, $now);
        }
    }

    private function insertMetric(int $serverId, string $type, float $value, string $datetime): void
    {
        $insert = $this->pdo->prepare("INSERT INTO server_metrics (server_id, type, value, measured_at)
                                       VALUES (:server_id, :type, :value, :measured_at)");
        $insert->execute([
            ':server_id' => $serverId,
            ':type' => $type,
            ':value' => $value,
            ':measured_at' => $datetime,
        ]);
    }

    private function emptyPingStats(string $status): array
    {
        return [
            'status' => $status,
            'latency' => null,
            'latency_min' => null,
            'latency_max' => null,
            'packets_sent' => $this->pingPacketCount,
            'packets_received' => 0,
            'packet_loss_percent' => 100.0,
        ];
    }

    private function getDiskUsageViaSSH(array $server): ?float
    {
        if (empty($server['ssh_user']) || empty($server['ssh_password']) || empty($server['ssh_port'])) {
            return null;
        }

        try {
            $server['ssh_password'] = decrypt($server['ssh_password']);
            $ssh = new SSH2($server['hostname'], (int) $server['ssh_port']);
            if (!$ssh->login($server['ssh_user'], $server['ssh_password'])) {
                $this->updateSshOk((int) $server['id'], false);
                return null;
            }

            $this->updateSshOk((int) $server['id'], true);
            $detectedOs = $this->detectOsViaSSH($ssh);
            if ($detectedOs !== null) {
                $this->updateOsIfChanged((int) $server['id'], $server['os'] ?? null, $detectedOs);
            }

            $osForDisk = $detectedOs ?? ($server['os'] ?? '');
            if ($osForDisk && stripos($osForDisk, 'windows') !== false) {
                $output = $ssh->exec("wmic logicaldisk where \"DeviceID='C:'\" get FreeSpace,Size /format:csv");
                $lines = explode("\n", trim($output));
                if (count($lines) < 2) {
                    return null;
                }

                $parts = str_getcsv($lines[1]);
                $free = (float) $parts[1];
                $total = (float) $parts[2];
            } else {
                $output = $ssh->exec('df -P / | tail -1');
                $cols = preg_split('/\s+/', trim($output));
                $usedPercent = rtrim($cols[4] ?? '', '%');

                return is_numeric($usedPercent) ? (float) $usedPercent : null;
            }

            if ($total > 0) {
                $used = 100 - (($free / $total) * 100);
                return round($used, 1);
            }
        } catch (\Throwable) {
            $this->updateSshOk((int) $server['id'], false);
            return null;
        }

        return null;
    }

    private function detectOsViaSSH(SSH2 $ssh): ?string
    {
        $output = trim((string) $ssh->exec('cat /etc/os-release 2>/dev/null'));
        if ($output !== '') {
            $values = [];
            foreach (preg_split('/\R/', $output) ?: [] as $line) {
                if (!str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);
                $values[$key] = trim($value, " \t\n\r\0\x0B\"'");
            }

            if (!empty($values['PRETTY_NAME'])) {
                return $values['PRETTY_NAME'];
            }

            $fallback = trim(($values['NAME'] ?? '') . ' ' . ($values['VERSION'] ?? ''));
            if ($fallback !== '') {
                return $fallback;
            }
        }

        $output = trim((string) $ssh->exec('powershell -Command "(Get-CimInstance Win32_OperatingSystem).Caption"'));
        if ($output !== '' && !$this->looksLikeCommandError($output)) {
            return $output;
        }

        $output = trim((string) $ssh->exec('ver'));
        return $output !== '' && !$this->looksLikeCommandError($output) ? $output : null;
    }

    private function looksLikeCommandError(string $output): bool
    {
        $output = strtolower($output);

        return str_contains($output, 'not found')
            || str_contains($output, 'not recognized')
            || str_contains($output, 'introuvable')
            || str_contains($output, 'erreur');
    }

    private function updateOsIfChanged(int $id, ?string $previousOs, string $detectedOs): void
    {
        $previousOs = trim((string) $previousOs);
        $detectedOs = trim($detectedOs);

        if ($detectedOs === '' || $detectedOs === $previousOs) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->history->recordChange(
            $id,
            'os',
            $previousOs !== '' ? $previousOs : null,
            $detectedOs,
            'OS detecte passe de ' . ($previousOs !== '' ? $previousOs : 'inconnu') . ' a ' . $detectedOs . '.',
            $now
        );

        $stmt = $this->pdo->prepare("UPDATE servers SET os = :os WHERE id = :id");
        $stmt->execute([
            ':os' => $detectedOs,
            ':id' => $id,
        ]);
    }

    private function updateSshOk(int $id, bool $ok, ?string $createdAt = null): void
    {
        $newStatus = $ok ? 'success' : 'fail';
        $previousStatus = $this->getCurrentSshStatus($id);
        $createdAt ??= date('Y-m-d H:i:s');

        $this->history->recordChange(
            $id,
            'ssh_status',
            $previousStatus,
            $newStatus,
            'Statut SSH passe de ' . ($previousStatus ?? 'inconnu') . ' a ' . $newStatus . '.',
            $createdAt
        );

        $stmt = $this->pdo->prepare("UPDATE servers SET ssh_status = :status WHERE id = :id");
        $stmt->execute([
            ':status' => $newStatus,
            ':id' => $id,
        ]);
    }

    private function getCurrentSshStatus(int $id): ?string
    {
        $stmt = $this->pdo->prepare("SELECT ssh_status FROM servers WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (string) $value : null;
    }
}
