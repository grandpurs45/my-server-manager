<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/header.php';

use MSM\SettingsManager;

$stmt = $pdo->query("SELECT * FROM servers ORDER BY name ASC");
$servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="p-6">
    <h1 class="text-2xl font-bold mb-6 flex justify-between items-center">
        Supervision des serveurs
        <form method="post" action="update-status.php">
            <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded hover:bg-blue-700">
                🔄 Mettre à jour les statuts
            </button>
        </form>
    </h1>

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
