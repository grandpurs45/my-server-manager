#!/usr/bin/env php
<?php
declare(strict_types=1);

chdir(__DIR__ . '/..');

$requiredPhpVersion = '8.0.0';
$requiredExtensions = ['pdo_mysql', 'openssl', 'mbstring'];
$recommendedExtensions = ['zip'];
$requiredCommands = ['git', 'composer', 'ping'];
$recommendedCommands = ['unzip', 'ssh'];
$recommendedMemoryMb = 900;
$recommendedDiskMb = 5120;

$hasErrors = false;
$hasWarnings = false;
$recommendedActions = [];

function printResult(string $status, string $label, string $detail = ''): void
{
    $line = sprintf('[%s] %s', $status, $label);
    if ($detail !== '') {
        $line .= ' - ' . $detail;
    }
    echo $line . PHP_EOL;
}

function markError(string $label, string $detail = ''): void
{
    global $hasErrors;
    $hasErrors = true;
    printResult('FAIL', $label, $detail);
}

function markWarning(string $label, string $detail = ''): void
{
    global $hasWarnings;
    $hasWarnings = true;
    printResult('WARN', $label, $detail);
}

function addRecommendedAction(string $action): void
{
    global $recommendedActions;

    if (!in_array($action, $recommendedActions, true)) {
        $recommendedActions[] = $action;
    }
}

function markOk(string $label, string $detail = ''): void
{
    printResult('OK', $label, $detail);
}

function commandExists(string $command): bool
{
    $isWindows = PHP_OS_FAMILY === 'Windows';
    $lookupCommand = $isWindows
        ? 'where ' . escapeshellarg($command) . ' 2>NUL'
        : 'command -v ' . escapeshellarg($command) . ' 2>/dev/null';
    $output = [];
    $exitCode = 1;

    exec($lookupCommand, $output, $exitCode);

    return $exitCode === 0 && count($output) > 0;
}

function serviceIsActive(string $service): bool
{
    if (PHP_OS_FAMILY === 'Windows' || !commandExists('systemctl')) {
        return false;
    }

    $exitCode = 1;
    exec('systemctl is-active --quiet ' . escapeshellarg($service), $output, $exitCode);

    return $exitCode === 0;
}

function readEnvFile(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }

    return $values;
}

function getMemoryTotalMb(): ?int
{
    if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/meminfo')) {
        $content = file_get_contents('/proc/meminfo') ?: '';
        if (preg_match('/^MemTotal:\s+(\d+)\s+kB/m', $content, $matches) === 1) {
            return (int) round(((int) $matches[1]) / 1024);
        }
    }

    return null;
}

echo 'MSM prerequisites check' . PHP_EOL;
echo '=======================' . PHP_EOL . PHP_EOL;

if (version_compare(PHP_VERSION, $requiredPhpVersion, '>=')) {
    markOk('PHP version', PHP_VERSION);
} else {
    markError('PHP version', PHP_VERSION . ' installed, ' . $requiredPhpVersion . ' required');
}

if (function_exists('exec')) {
    markOk('PHP function exec');
} else {
    markError('PHP function exec', 'disabled; MSM cannot run ping checks');
}

foreach ($requiredExtensions as $extension) {
    if (extension_loaded($extension)) {
        markOk('PHP extension ' . $extension);
    } else {
        markError('PHP extension ' . $extension, 'missing');
    }
}

foreach ($recommendedExtensions as $extension) {
    if (extension_loaded($extension)) {
        markOk('PHP extension ' . $extension);
    } else {
        markWarning('PHP extension ' . $extension, 'missing; Composer may fall back to source installs');
    }
}

foreach ($requiredCommands as $command) {
    if (commandExists($command)) {
        markOk('Command ' . $command);
    } else {
        markError('Command ' . $command, 'not found in PATH');
    }
}

foreach ($recommendedCommands as $command) {
    if (commandExists($command)) {
        markOk('Command ' . $command);
    } else {
        markWarning('Command ' . $command, 'not found in PATH; Composer may fall back to source installs');
    }
}

if (commandExists('mysql') || commandExists('mariadb')) {
    markOk('MariaDB/MySQL client');
} else {
    markWarning('MariaDB/MySQL client', 'not found in PATH; server may still be available remotely');
}

$apacheAvailable = commandExists('apache2')
    || commandExists('apachectl')
    || commandExists('httpd')
    || commandExists('apache');

if ($apacheAvailable) {
    markOk('Apache command');
} else {
    markWarning('Apache command', 'not found in PATH; check your web server package manually');
}

if (PHP_OS_FAMILY !== 'Windows' && commandExists('systemctl')) {
    if (serviceIsActive('apache2') || serviceIsActive('httpd')) {
        markOk('Apache service', 'active');
    } elseif ($apacheAvailable) {
        markWarning('Apache service', 'installed but not active; run systemctl enable --now apache2 or httpd');
    }
}

$diskFreeMb = (int) floor((disk_free_space(__DIR__ . '/..') ?: 0) / 1024 / 1024);
if ($diskFreeMb >= $recommendedDiskMb) {
    markOk('Available disk space', $diskFreeMb . ' MB');
} else {
    markWarning('Available disk space', $diskFreeMb . ' MB available, ' . $recommendedDiskMb . ' MB recommended');
}

$memoryTotalMb = getMemoryTotalMb();
if ($memoryTotalMb === null) {
    markWarning('System memory', 'could not be detected automatically on this OS');
} elseif ($memoryTotalMb >= $recommendedMemoryMb) {
    markOk('System memory', $memoryTotalMb . ' MB');
} else {
    markWarning('System memory', $memoryTotalMb . ' MB detected, ' . $recommendedMemoryMb . ' MB recommended');
}

if (is_file('.env')) {
    markOk('Local config .env', 'present');
    $env = readEnvFile('.env');

    foreach (['MSM_DB_HOST', 'MSM_DB_NAME', 'MSM_DB_USER', 'MSM_SECRET_KEY'] as $key) {
        if (($env[$key] ?? '') !== '') {
            markOk('.env value ' . $key);
        } else {
            markWarning('.env value ' . $key, 'missing or empty');
            addRecommendedAction('Completer `' . $key . '` dans .env.');
        }
    }
} else {
    markWarning('Local config .env', 'missing; copy .env.example to .env before running MSM');
    addRecommendedAction('Creer .env avec `php scripts/setup.php --init-env`.');
    addRecommendedAction('Generer les commandes SQL avec `php scripts/setup.php --db-sql`, puis reporter les valeurs MSM_DB_* dans .env.');
}

if (is_dir('logs')) {
    if (is_writable('logs')) {
        markOk('logs directory', 'writable');
    } else {
        markWarning('logs directory', 'exists but is not writable by the current user');
        addRecommendedAction('Corriger les permissions de `logs/` pour l utilisateur qui lance les checks.');
    }
} else {
    markWarning('logs directory', 'missing; create it before configuring scheduled checks');
    addRecommendedAction('Creer les fichiers de logs avec `php scripts/setup.php --init-logs`.');
}

if (is_dir('migrations') && is_readable('migrations')) {
    markOk('migrations directory', 'readable');
} else {
    markError('migrations directory', 'missing or not readable');
}

echo PHP_EOL;
if ($recommendedActions !== []) {
    echo 'Recommended actions' . PHP_EOL;
    echo '===================' . PHP_EOL;
    foreach ($recommendedActions as $index => $action) {
        echo ($index + 1) . '. ' . $action . PHP_EOL;
    }
    echo PHP_EOL;
}

if ($hasErrors) {
    echo 'Result: prerequisites are not satisfied. Fix FAIL items before installing MSM.' . PHP_EOL;
    exit(1);
}

if ($hasWarnings) {
    echo 'Result: required prerequisites are satisfied, but WARN items should be reviewed.' . PHP_EOL;
    exit(0);
}

echo 'Result: all checked prerequisites are satisfied.' . PHP_EOL;
exit(0);
