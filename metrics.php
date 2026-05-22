<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use MSM\PrometheusExporter;

header('Content-Type: text/plain; version=0.0.4; charset=utf-8');

$exporter = new PrometheusExporter($pdo);
echo $exporter->render();
