<?php
require_once __DIR__ . '/config.php';

$host = msmEnv('MSM_DB_HOST', 'localhost');
$port = msmEnv('MSM_DB_PORT', '3306');
$db = msmEnv('MSM_DB_NAME', 'msm');
$user = msmEnv('MSM_DB_USER', 'root');
$pass = msmEnv('MSM_DB_PASS', '');
$charset = msmEnv('MSM_DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log('MSM database connection failed: ' . $e->getMessage());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Erreur de connexion a la base de donnees.\n");
        exit(1);
    }

    http_response_code(500);
    echo "Erreur de connexion a la base de donnees.";
    exit;
}
