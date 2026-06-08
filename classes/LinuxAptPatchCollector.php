<?php
namespace MSM;

require_once __DIR__ . '/../includes/crypto.php';

use phpseclib3\Net\SSH2;

class LinuxAptPatchCollector
{
    public function collect(array $server): PatchCheckResult
    {
        $startedAt = microtime(true);
        $checkedAt = date('Y-m-d H:i:s');
        $serverId = (int) $server['id'];
        $collector = ($server['target_type'] ?? '') === 'proxmox' ? 'proxmox_apt' : 'apt';

        try {
            if (empty($server['ssh_enabled']) || empty($server['ssh_user']) || empty($server['ssh_password'])) {
                return $this->result(
                    $serverId,
                    $collector,
                    'error',
                    [],
                    false,
                    $checkedAt,
                    $startedAt,
                    'SSH non configure ou desactive pour cette cible.'
                );
            }

            $ssh = new SSH2($server['hostname'], (int) ($server['ssh_port'] ?? 22));
            $ssh->setTimeout(20);

            if (!$ssh->login($server['ssh_user'], decrypt($server['ssh_password']))) {
                return $this->result(
                    $serverId,
                    $collector,
                    'error',
                    [],
                    false,
                    $checkedAt,
                    $startedAt,
                    'Connexion SSH echouee.'
                );
            }

            if (trim($ssh->exec('command -v apt-get')) === '') {
                return $this->result(
                    $serverId,
                    $collector,
                    'unsupported',
                    [],
                    false,
                    $checkedAt,
                    $startedAt,
                    'Collecteur apt non applicable : apt-get introuvable.'
                );
            }

            $output = $ssh->exec('LC_ALL=C apt list --upgradable 2>/dev/null');
            $updates = $this->parseAptListUpgradable($output);
            $rebootRequired = trim($ssh->exec('test -f /var/run/reboot-required && echo yes || echo no')) === 'yes';

            $status = 'ok';
            if ($rebootRequired || $this->countByType($updates, 'security') > 0) {
                $status = 'critical';
            } elseif ($updates !== []) {
                $status = 'warning';
            }

            return $this->result($serverId, $collector, $status, $updates, $rebootRequired, $checkedAt, $startedAt);
        } catch (\Throwable $e) {
            return $this->result(
                $serverId,
                $collector,
                'error',
                [],
                false,
                $checkedAt,
                $startedAt,
                $e->getMessage()
            );
        }
    }

    private function parseAptListUpgradable(string $output): array
    {
        $updates = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with(strtolower($line), 'listing...')) {
                continue;
            }

            if (!preg_match('/^([^\/\s]+)\/([^\s]+)\s+([^\s]+)\s+([^\s]+)(?:\s+\[(.+)\])?/u', $line, $matches)) {
                continue;
            }

            $source = $matches[2] ?? '';
            $candidateVersion = $matches[3] ?? null;
            $metadata = $matches[5] ?? '';
            $installedVersion = null;

            if (preg_match('/(?:upgradable from|pouvant.*depuis)\s*:\s*([^\]]+)/iu', $metadata, $versionMatch)) {
                $installedVersion = trim($versionMatch[1]);
            }

            $type = str_contains(strtolower($source), 'security') ? 'security' : 'normal';

            $updates[] = [
                'type' => $type,
                'package' => $matches[1],
                'installed_version' => $installedVersion,
                'candidate_version' => $candidateVersion,
                'source' => $source,
                'severity' => $type === 'security' ? 'security' : null,
            ];
        }

        return $updates;
    }

    private function result(
        int $serverId,
        string $collector,
        string $status,
        array $updates,
        bool $rebootRequired,
        string $checkedAt,
        float $startedAt,
        ?string $errorMessage = null
    ): PatchCheckResult {
        return new PatchCheckResult(
            serverId: $serverId,
            collector: $collector,
            status: $status,
            normalUpdatesCount: $this->countByType($updates, 'normal'),
            securityUpdatesCount: $this->countByType($updates, 'security'),
            rebootRequired: $rebootRequired,
            checkedAt: $checkedAt,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            errorMessage: $errorMessage,
            updates: $updates
        );
    }

    private function countByType(array $updates, string $type): int
    {
        return count(array_filter($updates, fn (array $update): bool => ($update['type'] ?? '') === $type));
    }
}
