<?php
namespace MSM;

require_once __DIR__ . '/../includes/crypto.php';

use phpseclib3\Net\SSH2;

class LinuxOsLifecycleCollector
{
    public function __construct(private readonly OsLifecycleRepository $repository)
    {
    }

    public function collect(array $server): array
    {
        $checkedAt = date('Y-m-d H:i:s');
        $serverId = (int) $server['id'];

        try {
            if (empty($server['ssh_enabled']) || empty($server['ssh_user']) || empty($server['ssh_password'])) {
                return $this->error($serverId, $checkedAt, 'SSH non configure ou desactive pour cette cible.');
            }

            $ssh = new SSH2($server['hostname'], (int) ($server['ssh_port'] ?? 22));
            $ssh->setTimeout(20);

            if (!$ssh->login($server['ssh_user'], decrypt($server['ssh_password']))) {
                return $this->error($serverId, $checkedAt, 'Connexion SSH echouee.');
            }

            $output = trim($ssh->exec('cat /etc/os-release 2>/dev/null'));
            if ($output === '') {
                return $this->error($serverId, $checkedAt, '/etc/os-release introuvable ou illisible.');
            }

            $osRelease = $this->parseOsRelease($output);
            $family = $this->normalizeFamily($osRelease['ID'] ?? null);
            $version = $this->normalizeVersion($osRelease['VERSION_ID'] ?? null);
            $codename = $this->normalizeCodename(
                $osRelease['VERSION_CODENAME'] ?? ($osRelease['UBUNTU_CODENAME'] ?? null)
            );
            $prettyName = $osRelease['PRETTY_NAME'] ?? null;
            $reference = $this->repository->findReference($family, $version, $codename);

            return $this->result(
                $serverId,
                $family,
                $version,
                $codename,
                $prettyName,
                $reference,
                $checkedAt
            );
        } catch (\Throwable $e) {
            return $this->error($serverId, $checkedAt, $e->getMessage());
        }
    }

    private function parseOsRelease(string $output): array
    {
        $values = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $values[trim($key)] = stripcslashes($value);
        }

        return $values;
    }

    private function result(
        int $serverId,
        ?string $family,
        ?string $version,
        ?string $codename,
        ?string $prettyName,
        ?array $reference,
        string $checkedAt
    ): array {
        $supportEndsAt = $reference['support_ends_at'] ?? null;
        $upgradeTargetVersion = $reference['upgrade_target_version'] ?? null;

        return [
            'server_id' => $serverId,
            'os_family' => $family,
            'os_version' => $version,
            'os_codename' => $codename,
            'os_pretty_name' => $prettyName,
            'support_status' => $this->supportStatus($supportEndsAt),
            'support_ends_at' => $supportEndsAt,
            'upgrade_available' => $upgradeTargetVersion !== null && $upgradeTargetVersion !== '',
            'upgrade_target_version' => $upgradeTargetVersion,
            'upgrade_target_label' => $reference['upgrade_target_label'] ?? null,
            'checked_at' => $checkedAt,
            'error_message' => $reference ? null : 'Reference de cycle de vie inconnue pour cet OS.',
        ];
    }

    private function error(int $serverId, string $checkedAt, string $message): array
    {
        return [
            'server_id' => $serverId,
            'support_status' => 'unknown',
            'upgrade_available' => false,
            'checked_at' => $checkedAt,
            'error_message' => $message,
        ];
    }

    private function supportStatus(?string $supportEndsAt): string
    {
        if ($supportEndsAt === null || $supportEndsAt === '') {
            return 'unknown';
        }

        try {
            $today = new \DateTimeImmutable('today');
            $end = new \DateTimeImmutable($supportEndsAt);
        } catch (\Exception) {
            return 'unknown';
        }

        if ($end < $today) {
            return 'eol';
        }

        if ($end <= $today->modify('+180 days')) {
            return 'eol_soon';
        }

        return 'supported';
    }

    private function normalizeFamily(?string $family): ?string
    {
        if ($family === null) {
            return null;
        }

        $family = strtolower(trim($family));

        return match ($family) {
            'ubuntu' => 'ubuntu',
            'debian' => 'debian',
            'rocky', 'rocky_linux' => 'rocky',
            default => str_replace('-', '_', $family),
        };
    }

    private function normalizeVersion(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $version = trim($version);

        return $version === '' ? null : $version;
    }

    private function normalizeCodename(?string $codename): ?string
    {
        if ($codename === null) {
            return null;
        }

        $codename = strtolower(trim($codename));
        $codename = str_replace([' ', '-'], '_', $codename);

        return $codename === '' ? null : $codename;
    }
}
