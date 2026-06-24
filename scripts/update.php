<?php

require_once __DIR__ . '/../classes/UpdateAssistant.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$options = array_slice($argv, 1);
$unknownOptions = array_values(array_filter(
    $options,
    static fn(string $option): bool => !in_array($option, ['--check', '--apply', '--yes', '--help', '-h'], true)
        && !str_starts_with($option, '--target=')
        && !str_starts_with($option, '--backup-dir=')
));

if ($unknownOptions !== []) {
    fwrite(STDERR, 'Option(s) inconnue(s): ' . implode(', ', $unknownOptions) . PHP_EOL);
    exit(2);
}

if (in_array('--help', $options, true) || in_array('-h', $options, true)) {
    echo "MSM update assistant\n";
    echo "Usage: php scripts/update.php [--check|--apply] [--target=vX.Y.Z] [--backup-dir=PATH] [--yes]\n\n";
    echo "Options:\n";
    echo "  --check             Verifie l instance et affiche le plan sans rien modifier.\n";
    echo "  --target=vX.Y.Z     Fixe la release cible attendue.\n";
    echo "  --backup-dir=PATH   Utilise un dossier de sauvegarde externe personnalise.\n";
    echo "  --apply             Sauvegarde puis applique la release cible.\n";
    echo "  --yes               Confirme le mode --apply sans question interactive.\n";
    echo "  --help              Affiche cette aide.\n";
    exit(0);
}

$target = readUpdateOption($options, '--target');
$backupDir = readUpdateOption($options, '--backup-dir');
$apply = in_array('--apply', $options, true);
$check = in_array('--check', $options, true);
$yes = in_array('--yes', $options, true);

$assistant = new UpdateAssistant(__DIR__ . '/..');

if ($apply && $check) {
    fwrite(STDERR, "Choisir soit --check, soit --apply.\n");
    exit(2);
}

if ($yes && !$apply) {
    fwrite(STDERR, "L option --yes est uniquement valide avec --apply.\n");
    exit(2);
}

if ($apply) {
    if ($target === null) {
        fwrite(STDERR, "Le mode --apply exige --target=vX.Y.Z.\n");
        exit(2);
    }

    exit($assistant->runApply($target, $backupDir, $yes));
}

exit($assistant->runCheck($target, $backupDir));

function readUpdateOption(array $options, string $name): ?string
{
    $prefix = $name . '=';
    foreach ($options as $option) {
        if (str_starts_with($option, $prefix)) {
            $value = trim(substr($option, strlen($prefix)));
            return $value !== '' ? $value : null;
        }
    }

    return null;
}
