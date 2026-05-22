<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
msmCsrfToken();
require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->query("
    SELECT servers.*, TIMESTAMPDIFF(SECOND, last_check, NOW()) AS last_check_age_seconds
    FROM servers
    ORDER BY name ASC
");
$servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

function formatLastCheck(?string $lastCheck, mixed $ageSeconds): array {
    if (empty($lastCheck)) {
        return [
            'text' => 'Jamais verifie',
            'color' => 'text-gray-400',
        ];
    }

    if ($ageSeconds === null || !is_numeric($ageSeconds)) {
        return [
            'text' => 'Date invalide',
            'color' => 'text-red-600',
        ];
    }

    $diffSeconds = (int) $ageSeconds;

    if ($diffSeconds < -60) {
        $futureMinutes = (int) ceil(abs($diffSeconds) / 60);

        return [
            'text' => "dans $futureMinutes min",
            'color' => 'text-yellow-600',
        ];
    }

    if ($diffSeconds < 60) {
        return [
            'text' => "a l'instant",
            'color' => 'text-green-600',
        ];
    }

    $diffMinutes = (int) floor($diffSeconds / 60);

    if ($diffMinutes < 60) {
        $text = "il y a $diffMinutes min";
    } elseif ($diffMinutes < 1440) {
        $hours = (int) floor($diffMinutes / 60);
        $text = "il y a $hours h";
    } else {
        $days = (int) floor($diffMinutes / 1440);
        $text = "il y a $days j";
    }

    if ($diffMinutes <= 2) {
        $color = 'text-green-600';
    } elseif ($diffMinutes <= 10) {
        $color = 'text-yellow-600';
    } else {
        $color = 'text-red-600';
    }

    return [
        'text' => $text,
        'color' => $color,
    ];
}
?>

<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-slate-900">
                Supervision des serveurs
            </h1>

            <a href="alerts-wall.php" target="_blank"
               class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                <span>Mur d'alertes</span>
            </a>
        </div>

        <form method="post" action="update-status.php">
            <?php echo msmCsrfField(); ?>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded shadow hover:bg-blue-700">
                <span>Mettre a jour les statuts</span>
            </button>
        </form>
    </div>

    <?php if (isset($_GET['checked'])): ?>
        <div class="mb-4 p-3 bg-green-100 text-green-800 text-sm rounded border border-green-300">
            Statuts des serveurs mis a jour avec succes.
        </div>
    <?php endif; ?>

    <?php if (empty($servers)): ?>
        <p class="text-gray-500 italic">Aucun serveur a superviser pour le moment.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach ($servers as $server):
                $lastCheckStatus = formatLastCheck(
                    $server['last_check'] ?? null,
                    $server['last_check_age_seconds'] ?? null
                );
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
                                    echo 'SSH desactive';
                                } elseif ($server['ssh_status'] === 'success') {
                                    echo 'SSH OK';
                                } else {
                                    echo 'Echec SSH';
                                }
                            ?>
                        </span>

                        <span class="text-xs font-semibold <?php echo $lastCheckStatus['color']; ?>"
                              title="<?php echo htmlspecialchars($server['last_check'] ?? ''); ?>">
                            Dernier check : <?php echo htmlspecialchars($lastCheckStatus['text']); ?>
                        </span>
                    </div>

                    <p class="text-sm text-gray-700">
                        <?php echo htmlspecialchars($server['os'] ?? 'OS inconnu'); ?>
                    </p>

                    <?php if (!is_null($server['latency'])): ?>
                        <p class="text-xs text-gray-500 mt-1 italic">
                            <?php echo (int) $server['latency']; ?> ms
                        </p>
                    <?php endif; ?>

                    <?php if (isset($diskUsages[$server['id']])): ?>
                        <div class="text-sm text-gray-700 mb-1"><?php echo $diskUsages[$server['id']]; ?>&nbsp;% utilise</div>
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
        button.innerText = 'Verification en cours...';
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
