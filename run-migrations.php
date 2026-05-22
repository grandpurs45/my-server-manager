<?php
require_once __DIR__ . '/includes/db.php';

$dir = __DIR__ . '/migrations';
$migrationFiles = glob($dir . '/*.sql');
sort($migrationFiles);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations_applied (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

foreach ($migrationFiles as $file) {
    $filename = basename($file);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations_applied WHERE filename = :filename");
    $stmt->execute([':filename' => $filename]);
    $alreadyApplied = $stmt->fetchColumn();

    if ($alreadyApplied) {
        echo "[SKIP] $filename deja appliquee.\n";
        continue;
    }

    echo "[RUN ] $filename...\n";
    $sql = file_get_contents($file);

    try {
        $pdo->exec($sql);
        $insert = $pdo->prepare("INSERT INTO migrations_applied (filename) VALUES (:filename)");
        $insert->execute([':filename' => $filename]);
        echo "[ OK ] $filename appliquee.\n";
    } catch (PDOException $e) {
        echo "[FAIL] Erreur avec $filename : " . $e->getMessage() . "\n";
        break;
    }
}
