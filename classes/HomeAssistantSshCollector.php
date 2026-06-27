<?php
namespace MSM;

require_once __DIR__ . '/../includes/crypto.php';

use phpseclib3\Net\SSH2;

class HomeAssistantSshCollector
{
    public function collect(array $server): HomeAssistantCheckResult
    {
        $startedAt = microtime(true);
        $checkedAt = date('Y-m-d H:i:s');
        $serverId = (int) $server['id'];

        try {
            if (($server['target_type'] ?? 'other') !== 'home_assistant') {
                return $this->result($serverId, null, 'unsupported', $checkedAt, $startedAt, errorMessage: 'Type de cible non supporte par le collecteur Home Assistant.');
            }

            if (empty($server['ssh_enabled']) || empty($server['ssh_user']) || empty($server['ssh_password'])) {
                return $this->result($serverId, null, 'error', $checkedAt, $startedAt, errorMessage: 'SSH non configure ou desactive.');
            }

            $ssh = new SSH2($server['hostname'], (int) ($server['ssh_port'] ?? 22));
            $ssh->setTimeout(20);

            if (!$ssh->login($server['ssh_user'], decrypt($server['ssh_password']))) {
                return $this->result($serverId, null, 'error', $checkedAt, $startedAt, errorMessage: 'Connexion SSH echouee.');
            }

            $haCliAvailable = trim((string) $ssh->exec('command -v ha 2>/dev/null')) !== '';
            $core = $haCliAvailable ? $this->jsonCommand($ssh, 'ha core info --raw-json 2>/dev/null') : [];
            $supervisor = $haCliAvailable ? $this->jsonCommand($ssh, 'ha supervisor info --raw-json 2>/dev/null') : [];
            $os = $haCliAvailable ? $this->jsonCommand($ssh, 'ha os info --raw-json 2>/dev/null') : [];
            $host = $haCliAvailable ? $this->jsonCommand($ssh, 'ha host info --raw-json 2>/dev/null') : [];

            $osRelease = $this->parseOsRelease((string) $ssh->exec('cat /etc/os-release 2>/dev/null'));
            $kernel = trim((string) $ssh->exec('uname -r 2>/dev/null'));

            $collectorParts = ['ssh'];
            if ($haCliAvailable) {
                $collectorParts[] = 'ha_cli';
            } else {
                $collectorParts[] = 'os_fallback';
            }

            $haVersion = $this->stringValue($core, ['version', 'version_current', 'core_version']);
            $haLatest = $this->stringValue($core, ['version_latest', 'latest_version', 'update_version']);
            $haUpdate = $this->boolValue($core, ['update_available']);

            $supervisorVersion = $this->stringValue($supervisor, ['version', 'version_current', 'supervisor_version']);
            $supervisorLatest = $this->stringValue($supervisor, ['version_latest', 'latest_version', 'update_version']);
            $supervisorUpdate = $this->boolValue($supervisor, ['update_available']);

            $osVersion = $this->stringValue($os, ['version', 'version_current', 'os_version'])
                ?? ($osRelease['pretty_name'] ?? null);
            $osLatest = $this->stringValue($os, ['version_latest', 'latest_version', 'update_version']);
            $osUpdate = $this->boolValue($os, ['update_available']);

            $installationType = $this->detectInstallationType($core, $supervisor, $os, $haCliAvailable);
            $hostOs = $this->stringValue($host, ['operating_system', 'os_name'])
                ?? ($osRelease['pretty_name'] ?? null);

            $error = null;
            if (!$haCliAvailable) {
                $error = 'Commande ha non disponible via SSH ; collecte limitee aux informations systeme.';
            }

            $status = ($haUpdate === true || $supervisorUpdate === true || $osUpdate === true)
                ? 'warning'
                : 'ok';

            return $this->result(
                $serverId,
                implode('+', $collectorParts),
                $status,
                $checkedAt,
                $startedAt,
                installationType: $installationType,
                haVersion: $haVersion,
                haLatestVersion: $haLatest,
                haUpdateAvailable: $haUpdate,
                supervisorVersion: $supervisorVersion,
                supervisorLatestVersion: $supervisorLatest,
                supervisorUpdateAvailable: $supervisorUpdate,
                osVersion: $osVersion,
                osLatestVersion: $osLatest,
                osUpdateAvailable: $osUpdate,
                hostOs: $hostOs,
                kernel: $kernel !== '' ? $kernel : null,
                errorMessage: $error,
                rawSummary: [
                    'core' => $this->compactSummary($core),
                    'supervisor' => $this->compactSummary($supervisor),
                    'os' => $this->compactSummary($os),
                    'host' => $this->compactSummary($host),
                ]
            );
        } catch (\Throwable $e) {
            return $this->result($serverId, null, 'error', $checkedAt, $startedAt, errorMessage: $e->getMessage());
        }
    }

    private function jsonCommand(SSH2 $ssh, string $command): array
    {
        $output = trim((string) $ssh->exec($command));
        if ($output === '') {
            return [];
        }

        $data = json_decode($output, true);
        if (!is_array($data)) {
            return [];
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }

        return $data;
    }

    private function parseOsRelease(string $raw): array
    {
        $values = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = strtolower(trim($key));
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if ($key !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    private function stringValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && trim((string) $data[$key]) !== '') {
                return mb_substr(trim((string) $data[$key]), 0, 190);
            }
        }

        return null;
    }

    private function boolValue(array $data, array $keys): ?bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            if (is_bool($value)) {
                return $value;
            }
            if (is_numeric($value)) {
                return (int) $value === 1;
            }
            if (is_string($value)) {
                return in_array(strtolower($value), ['1', 'true', 'yes', 'available'], true);
            }
        }

        return null;
    }

    private function detectInstallationType(array $core, array $supervisor, array $os, bool $haCliAvailable): ?string
    {
        if ($os !== []) {
            return 'Home Assistant OS';
        }
        if ($supervisor !== []) {
            return 'Home Assistant Supervised';
        }
        if ($core !== []) {
            return 'Home Assistant Core';
        }
        if ($haCliAvailable) {
            return 'Home Assistant CLI';
        }

        return null;
    }

    private function compactSummary(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'version',
            'version_latest',
            'update_available',
            'arch',
            'boot',
            'channel',
            'operating_system',
        ]));
    }

    private function result(
        int $serverId,
        ?string $collector,
        string $status,
        string $checkedAt,
        float $startedAt,
        ?string $installationType = null,
        ?string $haVersion = null,
        ?string $haLatestVersion = null,
        ?bool $haUpdateAvailable = null,
        ?string $supervisorVersion = null,
        ?string $supervisorLatestVersion = null,
        ?bool $supervisorUpdateAvailable = null,
        ?string $osVersion = null,
        ?string $osLatestVersion = null,
        ?bool $osUpdateAvailable = null,
        ?string $hostOs = null,
        ?string $kernel = null,
        ?string $errorMessage = null,
        ?array $rawSummary = null
    ): HomeAssistantCheckResult {
        return new HomeAssistantCheckResult(
            serverId: $serverId,
            collector: $collector,
            status: $status,
            installationType: $installationType,
            haVersion: $haVersion,
            haLatestVersion: $haLatestVersion,
            haUpdateAvailable: $haUpdateAvailable,
            supervisorVersion: $supervisorVersion,
            supervisorLatestVersion: $supervisorLatestVersion,
            supervisorUpdateAvailable: $supervisorUpdateAvailable,
            osVersion: $osVersion,
            osLatestVersion: $osLatestVersion,
            osUpdateAvailable: $osUpdateAvailable,
            hostOs: $hostOs,
            kernel: $kernel,
            checkedAt: $checkedAt,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            errorMessage: $errorMessage,
            rawSummary: $rawSummary
        );
    }
}
