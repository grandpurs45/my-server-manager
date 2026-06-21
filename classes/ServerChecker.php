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

    public function __construct(\PDO $pdo, bool $withMetrics = true)
    {
        $this->pdo = $pdo;
        $this->withMetrics = $withMetrics;
        $this->history = new ServerCheckHistoryRepository($pdo);
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
            return ['status' => 'down', 'latency' => null];
        }

        $pingCmd = \msmBuildPingCommand($ip, 1);
        if ($pingCmd === null) {
            return ['status' => 'down', 'latency' => null];
        }

        $isWindows = stripos(PHP_OS, 'WIN') === 0;

        exec($pingCmd, $output, $resultCode);

        if ($resultCode !== 0) {
            return ['status' => 'down', 'latency' => null];
        }

        $latency = null;
        foreach ($output as $line) {
            if ($isWindows && preg_match('/temps[=<]?\s*(\d+)\s*ms/i', $line, $matches)) {
                $latency = (int) $matches[1];
                break;
            }

            if (!$isWindows && preg_match('/(?:time|temps)[=<]?(\d+\.?\d*)\s*ms/i', $line, $matches)) {
                $latency = round((float) $matches[1]);
                break;
            }
        }

        return ['status' => 'up', 'latency' => $latency];
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

        $update = $this->pdo->prepare("UPDATE servers SET status = :status, last_check = :last_check, latency = :latency WHERE id = :id");
        $update->execute([
            ':status' => $status,
            ':last_check' => $now,
            ':latency' => $latency,
            ':id' => $server['id'],
        ]);

        if ($this->withMetrics) {
            if ($latency !== null) {
                $this->insertMetric((int) $server['id'], 'ping', $latency, $now);
            }

            $this->insertMetric((int) $server['id'], 'availability', $availability, $now);

            $diskUsage = $this->getDiskUsageViaSSH($server);
            if ($diskUsage !== null) {
                $this->insertMetric((int) $server['id'], 'disk', $diskUsage, $now);
            }
        }

        echo "[{$server['hostname']}] status=$status latency=" . ($latency ?? '-') . "\n";

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
