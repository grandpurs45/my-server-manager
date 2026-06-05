<?php
namespace MSM;

require_once __DIR__ . '/../includes/crypto.php';

use phpseclib3\Net\SSH2;

class LinuxDnfPatchCollector
{
    public function collect(array $server): PatchCheckResult
    {
        $startedAt = microtime(true);
        $checkedAt = date('Y-m-d H:i:s');
        $serverId = (int) $server['id'];

        try {
            if (empty($server['ssh_enabled']) || empty($server['ssh_user']) || empty($server['ssh_password'])) {
                return $this->result(
                    $serverId,
                    'error',
                    [],
                    false,
                    $checkedAt,
                    $startedAt,
                    'SSH non configure ou desactive pour cette cible.'
                );
            }

            $ssh = new SSH2($server['hostname'], (int) ($server['ssh_port'] ?? 22));
            $ssh->setTimeout(30);

            if (!$ssh->login($server['ssh_user'], decrypt($server['ssh_password']))) {
                return $this->result(
                    $serverId,
                    'error',
                    [],
                    false,
                    $checkedAt,
                    $startedAt,
                    'Connexion SSH echouee.'
                );
            }

            if (trim($ssh->exec('command -v dnf')) === '') {
                return $this->result(
                    $serverId,
                    'unsupported',
                    [],
                    false,
                    $checkedAt,
                    $startedAt,
                    'Collecteur dnf non applicable : dnf introuvable.'
                );
            }

            $updatesOutput = $ssh->exec('LC_ALL=C dnf -q check-update 2>/dev/null || true');
            $securityOutput = $ssh->exec('LC_ALL=C dnf -q updateinfo list security updates 2>/dev/null || true');
            $securityPackages = $this->parseSecurityPackageNames($securityOutput);
            $updates = $this->parseDnfCheckUpdate($updatesOutput, $securityPackages);
            $rebootRequired = $this->isRebootRequired($ssh);

            $status = 'ok';
            if ($rebootRequired || $this->countByType($updates, 'security') > 0) {
                $status = 'critical';
            } elseif ($updates !== []) {
                $status = 'warning';
            }

            return $this->result($serverId, $status, $updates, $rebootRequired, $checkedAt, $startedAt);
        } catch (\Throwable $e) {
            return $this->result(
                $serverId,
                'error',
                [],
                false,
                $checkedAt,
                $startedAt,
                $e->getMessage()
            );
        }
    }

    private function parseDnfCheckUpdate(string $output, array $securityPackages): array
    {
        $updates = [];
        $inObsoletingSection = false;

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $line = trim($line);

            if ($line === ''
                || str_starts_with($line, 'Last metadata expiration check:')
                || str_starts_with($line, 'Derniere verification de l')
            ) {
                continue;
            }

            if (str_starts_with($line, 'Obsoleting Packages')) {
                $inObsoletingSection = true;
                continue;
            }

            if ($inObsoletingSection) {
                continue;
            }

            $columns = preg_split('/\s+/', $line);
            if (!$columns || count($columns) < 3) {
                continue;
            }

            $packageWithArch = $columns[0];
            $candidateVersion = $columns[1] ?? null;
            $source = $columns[2] ?? null;
            $packageName = $this->stripArchitecture($packageWithArch);
            $isSecurity = $this->isSecurityPackage($packageName, $packageWithArch, $securityPackages);
            $type = $isSecurity ? 'security' : 'normal';

            $updates[] = [
                'type' => $type,
                'package' => $packageName,
                'installed_version' => null,
                'candidate_version' => $candidateVersion,
                'source' => $source,
                'severity' => $isSecurity ? 'security' : null,
            ];
        }

        return $updates;
    }

    private function parseSecurityPackageNames(string $output): array
    {
        $packages = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'Last metadata expiration check:')) {
                continue;
            }

            $columns = preg_split('/\s+/', $line);
            if (!$columns) {
                continue;
            }

            $packageToken = end($columns);
            if (!is_string($packageToken) || $packageToken === '') {
                continue;
            }

            $packages[] = $packageToken;
            $packages[] = $this->extractBaseNameFromNevra($packageToken);
        }

        return array_values(array_unique(array_filter($packages)));
    }

    private function stripArchitecture(string $packageWithArch): string
    {
        return preg_replace('/\.(noarch|x86_64|aarch64|armv7hl|i686|i386|src)$/', '', $packageWithArch) ?? $packageWithArch;
    }

    private function extractBaseNameFromNevra(string $packageToken): string
    {
        $packageToken = $this->stripArchitecture($packageToken);

        if (preg_match('/^(.+)-(?:\d|[0-9]+:)/', $packageToken, $matches)) {
            return $matches[1];
        }

        return $packageToken;
    }

    private function isSecurityPackage(string $packageName, string $packageWithArch, array $securityPackages): bool
    {
        foreach ($securityPackages as $securityPackage) {
            if ($securityPackage === $packageName || $securityPackage === $packageWithArch) {
                return true;
            }

            if (str_starts_with($securityPackage, $packageName . '-')
                || str_starts_with($securityPackage, $packageWithArch . '-')
            ) {
                return true;
            }
        }

        return false;
    }

    private function isRebootRequired(SSH2 $ssh): bool
    {
        if (trim($ssh->exec('command -v needs-restarting')) === '') {
            return false;
        }

        $output = trim($ssh->exec('needs-restarting -r >/dev/null 2>&1; echo $?'));

        return $output === '1';
    }

    private function result(
        int $serverId,
        string $status,
        array $updates,
        bool $rebootRequired,
        string $checkedAt,
        float $startedAt,
        ?string $errorMessage = null
    ): PatchCheckResult {
        return new PatchCheckResult(
            serverId: $serverId,
            collector: 'dnf',
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
