<?php
namespace MSM;

use phpseclib3\Net\SSH2;

class SecurityAudit {

    public static function getOpenPorts(array $server): array {
        require_once __DIR__ . '/../includes/crypto.php';

        $hostname = $server['hostname'];
        $port     = $server['ssh_port'];
        $user     = $server['ssh_user'];
        $password = decrypt($server['ssh_password']);

        $ssh = new SSH2($hostname, $port);

        if (!$ssh->login($user, $password)) {
            return ['error' => 'Connexion SSH échouée'];
        }

        // 1. Détecter le chemin de `ss`
        $path = trim($ssh->exec('command -v ss'));

        if (empty($path)) {
            return ['error' => 'La commande ss est introuvable sur ce serveur.'];
        }

        // 2. Exécuter ss -tuln depuis le chemin absolu
        $output = $ssh->exec("$path -tuln");
        file_put_contents(__DIR__ . '/../logs/ssh_ports_debug.log', $output);

        if (!$output) {
            return ['error' => 'Échec lors de l’exécution de ss -tuln'];
        }

        // 3. Parser les ports
        $lines = explode("\n", trim($output));
        unset($lines[0]); // En-tête

        $ports = [];

        foreach ($lines as $line) {
            // Nettoyage
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'Netid')) continue;

            // Découper par colonnes (espace ou tabulation)
            $cols = preg_split('/\s+/', $line);

            // Exemple attendu : tcp LISTEN 0 4096 127.0.0.1:22 ...
            if (count($cols) >= 5) {
                $proto = strtolower($cols[0]);
                $local = $cols[4];

                // Séparer adresse et port
                if (preg_match('/^(.*):(\d+)$/', $local, $matches)) {
                    $addr = $matches[1];
                    $port = $matches[2];

                    $ports[] = [
                        'proto' => strtoupper($proto),
                        'addr'  => $addr,
                        'port'  => $port
                    ];
                }
            }
        }
        return $ports;
    }

    public static function getFirewallStatus(array $server): array {
        require_once __DIR__ . '/../includes/crypto.php';

        $hostname = $server['hostname'];
        $port     = $server['ssh_port'];
        $user     = $server['ssh_user'];
        $password = decrypt($server['ssh_password']);

        $ssh = new SSH2($hostname, $port);

        if (!$ssh->login($user, $password)) {
            return ['error' => 'Connexion SSH échouée'];
        }

        // Vérifie si ufw est disponible
        $ufwPath = trim($ssh->exec('command -v ufw'));

        if (!$ufwPath) {
            return ['error' => 'ufw non installé sur ce serveur'];
        }

        $status = trim($ssh->exec('ufw status'));

        if (str_contains(strtolower($status), 'inactive')) {
            return ['status' => 'inactif', 'raw' => $status];
        }

        return ['status' => 'actif', 'raw' => $status];
    }

    public static function getSecurityUpdates(array $server): array {
        require_once __DIR__ . '/../includes/crypto.php';

        $hostname = $server['hostname'];
        $port     = $server['ssh_port'];
        $user     = $server['ssh_user'];
        $password = decrypt($server['ssh_password']);

        $ssh = new SSH2($hostname, $port);

        if (!$ssh->login($user, $password)) {
            return ['error' => 'Connexion SSH échouée'];
        }

        $output = $ssh->exec("apt list --upgradable 2>/dev/null");

        $output = str_replace("En train de lister... Fait", '', $output);
        $lines = explode("\n", trim($output));

        $updates = [
            'security' => [],
            'normal'   => []
        ];

        foreach ($lines as $line) {
            if (preg_match('/^(\S+)\/([^\s]+)\s+([^\s]+)\s+.*\[pouvant être mis à jour depuis/i', $line, $matches)) {
                $package = $matches[1];
                $source  = $matches[2];
                $version = $matches[3];

                $update = [
                    'package' => $package,
                    'version' => $version
                ];

                if (str_contains($source, 'security')) {
                    $updates['security'][] = $update;
                } else {
                    $updates['normal'][] = $update;
                }
            }
        }

        return $updates;
    }

    public static function isRebootRequired(array $server): bool {
        require_once __DIR__ . '/../includes/crypto.php';

        $hostname = $server['hostname'];
        $port     = $server['ssh_port'];
        $user     = $server['ssh_user'];
        $password = decrypt($server['ssh_password']);

        $ssh = new SSH2($hostname, $port);

        if (!$ssh->login($user, $password)) {
            return false;
        }

        $check = trim($ssh->exec('test -f /var/run/reboot-required && echo "yes" || echo "no"'));

        return $check === 'yes';
    }

    public static function rebootServer(array $server): bool {
        require_once __DIR__ . '/../includes/crypto.php';

        $ssh = new SSH2($server['hostname'], $server['ssh_port']);
        if (!$ssh->login($server['ssh_user'], decrypt($server['ssh_password']))) {
            return false;
        }

        $ssh->exec("sudo reboot");
        return true;
    }

    public static function canUseSudo(array $server): bool {
        require_once __DIR__ . '/../includes/crypto.php';

        $ssh = new SSH2($server['hostname'], $server['ssh_port']);
        if (!$ssh->login($server['ssh_user'], decrypt($server['ssh_password']))) {
            return false;
        }

        // Vérifie si l'utilisateur peut exécuter sudo sans mot de passe
        $test = trim($ssh->exec("sudo -n true 2>/dev/null && echo yes || echo no"));
        return $test === 'yes';
    }




}
