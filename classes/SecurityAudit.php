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
            $warnings = [];

            if (isset($ports['error'])) {
                $errors[] = $ports['error'];
                $ports = [];
            }

            if (isset($ports['warning'])) {
                $warnings[] = $ports['warning'];
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
            } elseif ($warnings !== []) {
                $status = 'warning';
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
                errorMessage: $errors !== [] || $warnings !== [] ? implode(' | ', array_merge($errors, $warnings)) : null
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
        if ($path !== '') {
            $output = $ssh->exec($path . ' -tuln');

            if (!$output) {
                return ['error' => 'Echec lors de l execution de ss -tuln'];
            }

            return self::parseSsOutput($output);
        }

        $netstatPath = trim($ssh->exec('command -v netstat'));
        if ($netstatPath !== '') {
            $output = $ssh->exec($netstatPath . ' -tuln');

            if (!$output) {
                return ['error' => 'Echec lors de l execution de netstat -tuln'];
            }

            return self::parseNetstatOutput($output);
        }

        return ['warning' => 'Aucune commande compatible pour lister les ports ouverts (ss/netstat introuvables).'];
    }

    private static function parseSsOutput(string $output): array
    {
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
            $parsed = self::parseLocalAddress($local);

            if ($parsed !== null) {
                $ports[] = [
                    'proto' => strtoupper($proto),
                    'addr' => $parsed['addr'],
                    'port' => $parsed['port'],
                ];
            }
        }

        return $ports;
    }

    private static function parseNetstatOutput(string $output): array
    {
        $ports = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'Active ') || str_starts_with($line, 'Proto')) {
                continue;
            }

            $cols = preg_split('/\s+/', $line);
            if (!$cols || count($cols) < 4) {
                continue;
            }

            $proto = strtolower($cols[0]);
            if (!str_starts_with($proto, 'tcp') && !str_starts_with($proto, 'udp')) {
                continue;
            }

            $parsed = self::parseLocalAddress($cols[3]);
            if ($parsed === null) {
                continue;
            }

            $ports[] = [
                'proto' => strtoupper(str_starts_with($proto, 'udp') ? 'udp' : 'tcp'),
                'addr' => $parsed['addr'],
                'port' => $parsed['port'],
            ];
        }

        return $ports;
    }

    private static function parseLocalAddress(string $local): ?array
    {
        $local = trim($local);
        if ($local === '') {
            return null;
        }

        if (preg_match('/^\[(.*)]:(\d+)$/', $local, $matches)) {
            return ['addr' => $matches[1], 'port' => (int) $matches[2]];
        }

        if (preg_match('/^(.*):(\d+)$/', $local, $matches)) {
            $address = $matches[1] !== '' ? $matches[1] : '*';
            return ['addr' => $address, 'port' => (int) $matches[2]];
        }

        return null;
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
