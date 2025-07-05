<?php
require_once __DIR__ . '/../includes/bootstrap.php';

use MSM\ServerChecker;

$checker = new ServerChecker($pdo, withMetrics: false);
$checker->run(); // avec métriques

header('Location: supervision.php?checked=1');
exit;