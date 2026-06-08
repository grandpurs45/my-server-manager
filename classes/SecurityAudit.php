<?php
namespace MSM;

use phpseclib3\Net\SSH2;

class SecurityAudit
{
    public function collect(array $server): SecurityCheckResult
    {
        $startedAt = microtime(true);
        $checkedAt = date('Y-m-d H:i:s');
        $serverId = (int) $server['id'];

        try {
            $ports = self::getOpenPorts($server);
            $firewall = self::getFirewallStatus($server);
            $errors = [];

            if (isset($ports['error'])) {
                $errors[] = $ports['error'];
                $ports = [];
            }

            if (isset($firewall['error'])) {
                $errors[] = $firewall['error'];
            }

            $normalizedPorts = array_map(fn (array $port): array => [
                'protocol' => $port['proto'] ?? 'unknown',
                'address' => $port['addr'] ?? '',
                'port' => (int) ($port['port'] ?? 0),
                'exposure' => self::portExposure($port['addr'] ?? ''),
            ], $ports);

            $firewallStatus = $firewall['status'] ?? null;
            $exposedPortsCount = count(array_filter($normalizedPorts, fn (array $port): bool => $port['exposure'] === 'public'));
            $status = 'ok';

            if ($errors !== []) {
                $status = 'error';
            } elseif (in_array($firewallStatus, ['inactif', 'not_installed', null], true) || $exposedPortsCount > 0) {
                $status = 'warning';
            }

            return new SecurityCheckResult(
                serverId: $serverId,
                status: $status,
                openPorts: $normalizedPorts,
                firewallStatus: $firewallStatus,
                checkedAt: $checkedAt,
                durationMs: (int) round((microtime(true) - $startedAt) * 1000),
                errorMessage: $errors !== [] ? implode(' | ', $errors) : null
            );
        } catch (\Throwable $e) {
            return new SecurityCheckResult(
                serverId: $serverId,
                status: 'error',
                openPorts: [],
                firewallStatus: null,
                checkedAt: $checkedAt,
                durationMs: (int) round((microtime(true) - $startedAt) * 1000),
                errorMessage: $e->getMessage()
            );
        }
    }

    public static function getOpenPorts(array $server): array
    {
        require_once __DIR__ . '/../includes/crypto.php';

        $ssh = self::connect($server);
        if (!$ssh) {
            return ['error' => 'Connexion SSH echouee'];
        }

        $path = trim($ssh->exec('command -v ss'));
        if ($path === '') {
            return ['error' => 'La commande ss est introuvable sur ce serveur.'];
        }

        $output = $ssh->exec($path . ' -tuln');

        if (!$output) {
            return ['error' => 'Echec lors de l execution de ss -tuln'];
        }

        $ports = [];
        $lines = explode("\n", trim($output));
        unset($lines[0]);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'Netid')) {
                continue;
            }

            $cols = preg_split('/\s+/', $line);
            if (!$cols || count($cols) < 5) {
                continue;
            }

            $proto = strtolower($cols[0]);
            $local = $cols[4];

            if (preg_match('/^(.*):(\d+)$/', $local, $matches)) {
                $ports[] = [
                    'proto' => strtoupper($proto),
                    'addr' => $matches[1],
                    'port' => $matches[2],
                ];
            }
        }

        return $ports;
    }

    public static function getFirewallStatus(array $server): array
    {
        require_once __DIR__ . '/../includes/crypto.php';

        $ssh = self::connect($server);
        if (!$ssh) {
            return ['error' => 'Connexion SSH echouee'];
        }

        $ufwPath = trim($ssh->exec('command -v ufw'));
        if ($ufwPath === '') {
            return ['status' => 'not_installed', 'raw' => null];
        }

        $status = trim($ssh->exec('ufw status'));
        if (str_contains(strtolower($status), 'inactive')) {
            return ['status' => 'inactif', 'raw' => $status];
        }

        return ['status' => 'actif', 'raw' => $status];
    }

    private static function connect(array $server): ?SSH2
    {
        if (empty($server['hostname']) || empty($server['ssh_user']) || empty($server['ssh_password'])) {
            return null;
        }

        $ssh = new SSH2($server['hostname'], (int) ($server['ssh_port'] ?? 22));

        return $ssh->login($server['ssh_user'], decrypt($server['ssh_password'])) ? $ssh : null;
    }

    private static function portExposure(string $address): string
    {
        $address = trim($address);

        if ($address === '127.0.0.1'
            || $address === '::1'
            || str_starts_with($address, '127.')
            || str_starts_with($address, '[::1]')
        ) {
            return 'local';
        }

        if ($address === '*'
            || $address === '0.0.0.0'
            || $address === '::'
            || $address === '[::]'
            || $address === '[::]:'
            || str_contains($address, '0.0.0.0')
        ) {
            return 'public';
        }

        return 'bound';
    }
}
