<?php

require_once __DIR__ . '/../classes/SetupAssistant.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

if (in_array('--help', array_slice($argv, 1), true) || in_array('-h', array_slice($argv, 1), true)) {
    echo "MSM update check\n";
    echo "Usage: php scripts/update-check.php\n\n";
    echo "Valide les points importants apres un git pull ou une montee de version.\n";
    exit(0);
}

$assistant = new SetupAssistant(__DIR__ . '/..');
exit($assistant->runUpdateCheck());
