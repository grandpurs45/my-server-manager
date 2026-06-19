<?php

require_once __DIR__ . '/../classes/SetupAssistant.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$options = array_slice($argv, 1);
$cronOnly = in_array('--cron', $options, true);
$systemdOnly = in_array('--systemd', $options, true);
$initEnv = in_array('--init-env', $options, true);
$initLogs = in_array('--init-logs', $options, true);
$dbSql = in_array('--db-sql', $options, true);
$migrate = in_array('--migrate', $options, true);
$systemdUser = readOptionValue($options, '--systemd-user') ?? 'www-data';
$systemdGroup = readOptionValue($options, '--systemd-group');

if (in_array('--help', $options, true) || in_array('-h', $options, true)) {
    echo "MSM setup assistant\n";
    echo "Usage: php scripts/setup.php [--cron] [--systemd] [--init-env] [--init-logs] [--db-sql] [--migrate]\n\n";
    echo "Options:\n";
    echo "  --cron       Affiche uniquement le bloc cron recommande pour ce dossier.\n";
    echo "  --systemd    Affiche les fichiers .service/.timer systemd recommandes.\n";
    echo "  --systemd-user=USER   Utilisateur systemd a utiliser, defaut www-data.\n";
    echo "  --systemd-group=GROUP Groupe systemd a utiliser, defaut identique a USER.\n";
    echo "  --init-env   Cree .env depuis .env.example si absent, avec une cle locale aleatoire.\n";
    echo "  --init-logs  Cree le dossier logs/ et les fichiers de logs attendus si absents.\n";
    echo "  --db-sql     Affiche les commandes SQL de creation de base/utilisateur.\n";
    echo "  --migrate    Lance explicitement apply_migrations.php.\n";
    echo "  --help       Affiche cette aide.\n";
    exit(0);
}

$assistant = new SetupAssistant(__DIR__ . '/..');

if ($systemdOnly) {
    $assistant->printSystemdInstructions($systemdUser, $systemdGroup);
    exit(0);
}

if ($initEnv) {
    exit($assistant->prepareLocalConfig());
}

if ($initLogs) {
    exit($assistant->prepareLogs());
}

if ($dbSql) {
    exit($assistant->printDatabaseInstructions());
}

if ($migrate) {
    exit($assistant->runMigrations());
}

exit($assistant->runSetup($cronOnly));

function readOptionValue(array $options, string $name): ?string
{
    $prefix = $name . '=';
    foreach ($options as $option) {
        if (str_starts_with($option, $prefix)) {
            $value = substr($option, strlen($prefix));
            return $value !== '' ? $value : null;
        }
    }

    return null;
}
