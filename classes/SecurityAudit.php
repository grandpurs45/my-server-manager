<?php
namespace MSM;

use phpseclib3\Net\SSH2;

class SecurityAudit
{
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
        file_put_contents(__DIR__ . '/../logs/ssh_ports_debug.log', $output);

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
            return ['error' => 'ufw non installe sur ce serveur'];
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
}
