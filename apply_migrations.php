<?php
require_once __DIR__ . '/includes/db.php';

$migrationsDir = __DIR__ . '/migrations';
$applied = [];

try {
    // On récupère les migrations déjà appliquées
    $stmt = $pdo->query("SELECT filename FROM migrations_applied");
    $applied = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    exit("Erreur lors de la récupération des migrations appliquées : " . $e->getMessage());
}

// On scanne le dossier
$files = glob($migrationsDir . '/*.sql');
sort($files); // tri chronologique

foreach ($files as $filePath) {
    $file = basename($filePath);

    if (in_array($file, $applied)) {
        echo "[SKIP] $file déjà appliqué.\n";
        continue;
    }

    $sql = file_get_contents($filePath);
    try {
        $pdo->exec($sql);
        $insert = $pdo->prepare("INSERT INTO migrations_applied (filename) VALUES (:file)");
        $insert->execute([':file' => $file]);
        echo "[OK]   $file appliqué avec succès.\n";
    } catch (PDOException $e) {
        echo "[ERREUR] $file : " . $e->getMessage() . "\n";
    }
}