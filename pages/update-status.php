<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';

use MSM\ServerChecker;

msmRequireValidCsrf('supervision.php');

$checker = new ServerChecker($pdo, withMetrics: false);
$checker->run(); // avec métriques

header('Location: supervision.php?checked=1');
exit;
