<?php
require_once __DIR__ . '/includes/db.php';

$dir = __DIR__ . '/migrations';
$migrationFiles = glob($dir . '/*.sql');
sort($migrationFiles);

foreach ($migrationFiles as $file) {
    $filename = basename($file);

    // Vérifie si déjà appliquée
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM migrations_applied WHERE filename = :filename");
    $stmt->execute([':filename' => $filename]);
    $alreadyApplied = $stmt->fetchColumn();

    if ($alreadyApplied) {
        echo "[SKIP] $filename déjà appliquée.\n";
        continue;
    }

    echo "[RUN ] $filename...\n";
    $sql = file_get_contents($file);
    try {
        $pdo->exec($sql);
        $insert = $pdo->prepare("INSERT INTO migrations_applied (filename, applied_at) VALUES (:filename, NOW())");
        $insert->execute([':filename' => $filename]);
        echo "[ OK ] $filename appliquée.\n";
    } catch (PDOException $e) {
        echo "[FAIL] Erreur avec $filename : " . $e->getMessage() . "\n";
        break;
    }
}