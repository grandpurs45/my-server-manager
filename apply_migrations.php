<?php
require_once __DIR__ . '/includes/db.php';

$migrationsDir = __DIR__ . '/migrations';
$applied = [];

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations_applied (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->query("SELECT filename FROM migrations_applied");
    $applied = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    fwrite(STDERR, "Erreur lors de l'initialisation des migrations : " . $e->getMessage() . PHP_EOL);
    exit(1);
}

$files = glob($migrationsDir . '/*.sql');
sort($files);
$hasErrors = false;

foreach ($files as $filePath) {
    $file = basename($filePath);

    if (in_array($file, $applied, true)) {
        echo "[SKIP] $file deja applique.\n";
        continue;
    }

    $sql = file_get_contents($filePath);
    if ($sql === false) {
        echo "[ERREUR] $file : lecture impossible.\n";
        $hasErrors = true;
        continue;
    }

    try {
        $pdo->exec($sql);
        $insert = $pdo->prepare("INSERT INTO migrations_applied (filename) VALUES (:file)");
        $insert->execute([':file' => $file]);
        echo "[OK]   $file applique avec succes.\n";
    } catch (PDOException $e) {
        echo "[ERREUR] $file : " . $e->getMessage() . "\n";
        $hasErrors = true;
    }
}

if ($hasErrors) {
    exit(1);
}
