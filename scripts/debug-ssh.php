#!/usr/bin/env php
<?php
declare(strict_types=1);

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/network.php';
require_once __DIR__ . '/../vendor/autoload.php';

use phpseclib3\Net\SSH2;

$target = $argv[1] ?? null;

if ($target === null || in_array($target, ['-h', '--help'], true)) {
    echo "Usage: php scripts/debug-ssh.php <server-id|server-name|hostname>\n";
    exit($target === null ? 1 : 0);
}

$stmt = $pdo->prepare(
    'SELECT id, name, hostname, ssh_port, ssh_user, ssh_password, ssh_enabled, ssh_status
     FROM servers
     WHERE id = :id OR name = :target OR hostname = :target
     ORDER BY id
     LIMIT 1'
);
$stmt->execute([
    ':id' => ctype_digit($target) ? (int) $target : 0,
    ':target' => $target,
]);

$server = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$server) {
    echo "[FAIL] Serveur introuvable pour '{$target}'.\n";
    exit(1);
}

$hostname = (string) ($server['hostname'] ?? '');
$port = (int) ($server['ssh_port'] ?? 22);
$user = (string) ($server['ssh_user'] ?? '');
$encryptedPassword = (string) ($server['ssh_password'] ?? '');

echo "MSM SSH debug\n";
echo "=============\n\n";
echo "Server ID      : {$server['id']}\n";
echo "Name           : {$server['name']}\n";
echo "Hostname       : {$hostname}\n";
echo "Port           : {$port}\n";
echo "SSH enabled    : " . ((int) ($server['ssh_enabled'] ?? 0) === 1 ? 'yes' : 'no') . "\n";
echo "SSH user       : " . ($user !== '' ? $user : '(missing)') . "\n";
echo "Stored status  : " . (($server['ssh_status'] ?? '') !== '' ? $server['ssh_status'] : '(empty)') . "\n\n";

if ((int) ($server['ssh_enabled'] ?? 0) !== 1) {
    echo "[FAIL] SSH est desactive pour cette cible dans MSM.\n";
    exit(1);
}

if ($hostname === '' || $port < 1 || $user === '' || $encryptedPassword === '') {
    echo "[FAIL] Configuration SSH incomplete dans MSM.\n";
    exit(1);
}

$resolvedIp = gethostbyname($hostname);
echo '[INFO] DNS/IP        : ' . ($resolvedIp !== $hostname ? $resolvedIp : 'resolution non confirmee') . "\n";

$pingCommand = msmBuildPingCommand($hostname, 1);
if ($pingCommand === null) {
    echo "[FAIL] MSM ping      : hostname invalide pour MSM\n";
} else {
    $pingOutput = [];
    $pingResultCode = 1;
    exec($pingCommand, $pingOutput, $pingResultCode);
    echo '[INFO] MSM ping cmd  : ' . $pingCommand . "\n";
    echo '[INFO] MSM ping code : ' . $pingResultCode . "\n";
    foreach ($pingOutput as $line) {
        echo '  ' . $line . "\n";
    }
}

$socketErrorNumber = 0;
$socketError = '';
$socket = @fsockopen($hostname, $port, $socketErrorNumber, $socketError, 5.0);
if (is_resource($socket)) {
    fclose($socket);
    echo "[OK] TCP            : {$hostname}:{$port} joignable\n";
} else {
    echo "[FAIL] TCP          : {$hostname}:{$port} injoignable ({$socketErrorNumber} {$socketError})\n";
    exit(1);
}

try {
    $password = decrypt($encryptedPassword);
} catch (Throwable $e) {
    echo '[FAIL] Decrypt       : ' . $e->getMessage() . "\n";
    exit(1);
}

if ($password === $encryptedPassword && strlen($encryptedPassword) > 40) {
    echo "[WARN] Decrypt       : le mot de passe semble ne pas avoir ete dechiffre. Verifier MSM_SECRET_KEY.\n";
} else {
    echo "[OK] Decrypt         : mot de passe charge depuis MSM\n";
}

$ssh = new SSH2($hostname, $port);
$ssh->setTimeout(10);

try {
    if (!$ssh->login($user, $password)) {
        echo "[FAIL] Login         : authentification refusee par phpseclib\n";
        $errors = method_exists($ssh, 'getErrors') ? $ssh->getErrors() : [];
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
        exit(1);
    }

    echo "[OK] Login           : authentification phpseclib reussie\n";

    $whoami = trim((string) $ssh->exec('whoami 2>/dev/null'));
    if ($whoami !== '') {
        echo "[OK] whoami          : {$whoami}\n";
    }

    $uname = trim((string) $ssh->exec('uname -a 2>/dev/null'));
    if ($uname !== '') {
        echo "[OK] uname           : {$uname}\n";
    }
} catch (Throwable $e) {
    echo '[FAIL] SSH exception : ' . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
