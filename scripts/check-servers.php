#!/usr/bin/env php
<?php
declare(strict_types=1);

// On se place à la racine du projet, au cas où
chdir(__DIR__ . '/..');

// Chargement de l'appli
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../autoloader.php';

use MSM\ServerChecker;

// withMetrics: true = on récupère aussi les métriques détaillées
$checker = new ServerChecker($pdo, withMetrics: true);
$checker->run();

// Petit message pour confirmer l'exécution en CLI
echo '[' . date('Y-m-d H:i:s') . "] Vérification des serveurs terminée.\n";
