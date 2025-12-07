<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/header.php';

use MSM\SettingsManager;

$stmt = $pdo->query("SELECT * FROM servers ORDER BY name ASC");
$servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Récupération des dernières valeurs de métrique disque
$metricsStmt = $pdo->query("
    SELECT server_id, value 
    FROM server_metrics 
    WHERE type = 'disk' 
    AND measured_at = (
        SELECT MAX(measured_at)
        FROM server_metrics sm2 
        WHERE sm2.server_id = server_metrics.server_id AND sm2.type = 'disk'
    )
");

$diskUsages = [];
foreach ($metricsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $diskUsages[$row['server_id']] = round($row['value']);
}
?>

<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <!-- Titre + bouton Mur d'alertes -->
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-slate-900">
                Supervision des serveurs
            </h1>

            <a href="alerts-wall.php" target="_blank"
               class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                <span>🛰️</span>
                <span>Mur d'alertes</span>
            </a>
        </div>

        <!-- Bouton Mettre à jour les statuts -->
        <form method="post" action="update-status.php">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded shadow hover:bg-blue-700">
                <span>🔄</span>
                <span>Mettre à jour les statuts</span>
            </button>
        </form>
    </div>
    <?php if (isset($_GET['checked'])): ?>
        <div class="mb-4 p-3 bg-green-100 text-green-800 text-sm rounded border border-green-300">
            Statuts des serveurs mis à jour avec succès.
        </div>
    <?php endif; ?>

    <?php if (empty($servers)): ?>
        <p class="text-gray-500 italic">Aucun serveur à superviser pour le moment.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach ($servers as $server): 
                $lastCheck = $server['last_check'];
                $checkAgeMinutes = $lastCheck ? round((time() - strtotime($lastCheck)) / 60) : null;
            ?>
                <div class="border rounded-xl p-4 shadow-sm bg-white">
                    <h2 class="text-lg font-semibold mb-1"><?php echo htmlspecialchars($server['name']); ?></h2>
                    <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($server['hostname']); ?></p>



                    <div class="flex items-center justify-between mb-2">
                        <?php if ($server['status'] === 'up'): ?>
                            <span class="inline-block px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">UP</span>
                        <?php else: ?>
                            <span class="inline-block px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">DOWN</span>
                        <?php endif; ?>
                        
                        <!--Affichage Pastille SSH -->
                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded mt-1
                            <?php
                                if (!$server['ssh_enabled']) {
                                    echo 'text-gray-500 bg-gray-100';
                                } elseif ($server['ssh_status'] === 'success') {
                                    echo 'text-green-700 bg-green-100';
                                } else {
                                    echo 'text-red-700 bg-red-100';
                                }
                            ?>">
                            <?php
                                if (!$server['ssh_enabled']) {
                                    echo 'SSH désactivé';
                                } elseif ($server['ssh_status'] === 'success') {
                                    echo 'SSH OK';
                                } else {
                                    echo 'Échec SSH';
                                }
                            ?>
                        </span>

                        <?php
                            if ($lastCheck) {
                                $lastCheckTime = strtotime($lastCheck);
                                $diffMinutes = round((time() - $lastCheckTime) / 60);
                                $agoText = $diffMinutes < 1 ? 'à l’instant' : "il y a $diffMinutes min";

                                if ($diffMinutes <= 2) {
                                    $color = 'text-green-600';
                                } elseif ($diffMinutes <= 10) {
                                    $color = 'text-yellow-600';
                                } else {
                                    $color = 'text-red-600';
                                }

                                echo "<span class='text-xs font-semibold $color'>Dernier check : $agoText</span>";
                            } else {
                                echo "<span class='text-xs text-gray-400 font-semibold'>Jamais vérifié</span>";
                            }
                        ?>
                    </div>

                    <p class="text-sm text-gray-700">
                        <?php echo htmlspecialchars($server['os'] ?? 'OS inconnu'); ?>
                    </p>

                    <?php if (!is_null($server['latency'])): ?>
                        <p class="text-xs text-gray-500 mt-1 italic">
                            ⏱️ <?php echo (int) $server['latency']; ?> ms
                        </p>
                    <?php endif; ?>
                    <!--Affichage Disque dur -->
                    <?php if (isset($diskUsages[$server['id']])): ?>
                        <div class="text-sm text-gray-700 mb-1">🗄️ <?php echo $diskUsages[$server['id']]; ?>&nbsp;% utilisé</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    const form = document.querySelector('form[action="update-status.php"]');
    const button = form.querySelector('button');

    form.addEventListener('submit', () => {
        button.disabled = true;
        button.innerText = "⏳ Vérification en cours...";
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
