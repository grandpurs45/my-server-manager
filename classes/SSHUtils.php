<?php
namespace MSM;

use phpseclib3\Net\SSH2;

class SSHUtils {
    /**
     * Tente de se connecter en SSH et retourne le nom complet de l'OS (ou null en cas d'erreur).
     */
    public static function detectOS(string $host, int $port, string $user, string $password): ?string {
        file_put_contents(__DIR__ . '/../logs/ssh-debug.log', "Tentative de connexion à $host:$port\n", FILE_APPEND);

        try {
            $ssh = new SSH2($host, $port);
            $ssh->setTimeout(5);

            if (!$ssh->login($user, $password)) {
                file_put_contents(__DIR__ . '/../logs/ssh-debug.log', "Échec login SSH\n", FILE_APPEND);
                return null;
            }

            // Tentative Linux classique
            $output = $ssh->exec('cat /etc/os-release');
            if (preg_match('/^PRETTY_NAME="(.+?)"$/m', $output, $matches)) {
                return $matches[1];
            }

            // Tentative Windows PowerShell
            $output = $ssh->exec('powershell -Command "(Get-CimInstance Win32_OperatingSystem).Caption"');
            if (!empty($output)) {
                return trim($output);
            }

            // Fallback Windows simple
            $output = $ssh->exec('ver');
            if (!empty($output)) {
                return trim($output);
            }

            return null;
        } catch (\Throwable $e) {
            file_put_contents(__DIR__ . '/../logs/ssh-debug.log', "Exception : " . $e->getMessage() . "\n", FILE_APPEND);
            return null;
        }
    }
}


