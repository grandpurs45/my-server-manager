<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
msmCsrfToken();

$supervisionInterval = (int) ($settings->get('supervision', 'check_interval_minutes') ?? 10);
if ($supervisionInterval < 1) {
    $supervisionInterval = 10;
}

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
    $diskUsages[(int) $row['server_id']] = round((float) $row['value']);
}

function msmSupervisionLastCheck(?string $lastCheck, mixed $ageSeconds, int $intervalMinutes): array
{
    if (empty($lastCheck)) {
        return [
            'text' => 'Jamais',
            'detail' => 'Aucun check connu',
            'badge' => 'bg-gray-100 text-gray-600 border-gray-200',
            'state' => 'unknown',
        ];
    }

    if ($ageSeconds === null || !is_numeric($ageSeconds)) {
        return [
            'text' => 'Invalide',
            'detail' => 'Date incoherente',
            'badge' => 'bg-red-50 text-red-700 border-red-200',
            'state' => 'critical',
        ];
    }

    $diffSeconds = (int) $ageSeconds;
    if ($diffSeconds < -60) {
        $futureMinutes = (int) ceil(abs($diffSeconds) / 60);

        return [
            'text' => 'Futur',
            'detail' => 'dans ' . $futureMinutes . ' min',
            'badge' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
            'state' => 'warning',
        ];
    }

    if ($diffSeconds < 60) {
        $detail = "a l'instant";
    } else {
        $diffMinutes = (int) floor($diffSeconds / 60);
        if ($diffMinutes < 60) {
            $detail = 'il y a ' . $diffMinutes . ' min';
        } elseif ($diffMinutes < 1440) {
            $detail = 'il y a ' . (int) floor($diffMinutes / 60) . ' h';
        } else {
            $detail = 'il y a ' . (int) floor($diffMinutes / 1440) . ' j';
        }
    }

    $maxFreshSeconds = max(300, $intervalMinutes * 60 * 2);
    $maxLateSeconds = max(900, $intervalMinutes * 60 * 4);

    if ($diffSeconds <= $maxFreshSeconds) {
        return [
            'text' => 'A jour',
            'detail' => $detail,
            'badge' => 'bg-green-50 text-green-700 border-green-200',
            'state' => 'ok',
        ];
    }

    if ($diffSeconds <= $maxLateSeconds) {
        return [
            'text' => 'En retard',
            'detail' => $detail,
            'badge' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
            'state' => 'warning',
        ];
    }

    return [
        'text' => 'Ancien',
        'detail' => $detail,
        'badge' => 'bg-red-50 text-red-700 border-red-200',
        'state' => 'critical',
    ];
}

function msmSupervisionStatusBadge(?string $status): string
{
    return match ($status) {
        'up' => '<span class="inline-flex items-center gap-1 rounded border border-green-200 bg-green-50 px-2 py-1 text-xs font-semibold text-green-700"><i data-lucide="check-circle" class="h-3.5 w-3.5"></i> Ping OK</span>',
        'down' => '<span class="inline-flex items-center gap-1 rounded border border-red-200 bg-red-50 px-2 py-1 text-xs font-semibold text-red-700"><i data-lucide="x-circle" class="h-3.5 w-3.5"></i> Ping KO</span>',
        default => '<span class="inline-flex items-center gap-1 rounded border border-gray-200 bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600"><i data-lucide="help-circle" class="h-3.5 w-3.5"></i> Inconnu</span>',
    };
}

function msmSupervisionSshBadge(array $server): string
{
    if (empty($server['ssh_enabled'])) {
        return '<span class="inline-flex items-center gap-1 rounded border border-gray-200 bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-500"><i data-lucide="minus-circle" class="h-3.5 w-3.5"></i> SSH desactive</span>';
    }

    if (($server['ssh_status'] ?? '') === 'success') {
        return '<span class="inline-flex items-center gap-1 rounded border border-green-200 bg-green-50 px-2 py-1 text-xs font-semibold text-green-700"><i data-lucide="terminal" class="h-3.5 w-3.5"></i> SSH OK</span>';
    }

    return '<span class="inline-flex items-center gap-1 rounded border border-red-200 bg-red-50 px-2 py-1 text-xs font-semibold text-red-700"><i data-lucide="alert-octagon" class="h-3.5 w-3.5"></i> SSH KO</span>';
}

function msmSupervisionModuleBadge(string $label, bool $enabled, string $activeClass): string
{
    $class = $enabled ? $activeClass : 'border-gray-200 bg-gray-50 text-gray-400';
    $suffix = $enabled ? 'actif' : 'off';

    return '<span class="inline-flex rounded border px-2 py-1 text-xs font-semibold ' . $class . '">' . htmlspecialchars($label) . ' ' . $suffix . '</span>';
}

function msmSupervisionPingLossBadge(mixed $loss): string
{
    if ($loss === null || !is_numeric($loss)) {
        return '<span class="font-semibold text-slate-500">-</span>';
    }

    $loss = (float) $loss;
    $class = $loss >= 50
        ? 'text-red-700'
        : ($loss > 0 ? 'text-yellow-700' : 'text-green-700');

    return '<span class="font-semibold ' . $class . '">' . htmlspecialchars(number_format($loss, 1, ',', '')) . ' %</span>';
}

$summary = [
    'total' => count($servers),
    'down' => 0,
    'ssh_errors' => 0,
    'stale' => 0,
];

foreach ($servers as $server) {
    if (($server['status'] ?? '') !== 'up') {
        $summary['down']++;
    }

    if (!empty($server['ssh_enabled']) && ($server['ssh_status'] ?? '') !== 'success') {
        $summary['ssh_errors']++;
    }

    $freshness = msmSupervisionLastCheck($server['last_check'] ?? null, $server['last_check_age_seconds'] ?? null, $supervisionInterval);
    if (in_array($freshness['state'], ['warning', 'critical'], true)) {
        $summary['stale']++;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-6">
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="mb-2 flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900">Supervision des serveurs</h1>
                <a href="alerts-wall.php" target="_blank"
                   class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                    <i data-lucide="monitor" class="h-3.5 w-3.5"></i>
                    <span>Mur d'alertes</span>
                </a>
            </div>
            <p class="text-sm text-slate-600">
                Vue des derniers resultats stockes : ping, SSH, modules actifs et fraicheur du check.
            </p>
        </div>

        <form method="post" action="update-status.php">
            <?php echo msmCsrfField(); ?>
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700">
                <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                <span>Mettre a jour les statuts</span>
            </button>
        </form>
    </div>

    <?php if (isset($_GET['checked'])): ?>
        <div class="mb-4 rounded border border-green-300 bg-green-100 p-3 text-sm text-green-800">
            Statuts des serveurs mis a jour avec succes.
        </div>
    <?php endif; ?>

    <div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Cibles</div>
            <div class="mt-2 text-2xl font-bold text-slate-900"><?= (int) $summary['total'] ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Ping KO</div>
            <div class="mt-2 text-2xl font-bold text-red-700"><?= (int) $summary['down'] ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">SSH KO</div>
            <div class="mt-2 text-2xl font-bold text-red-700"><?= (int) $summary['ssh_errors'] ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Checks anciens</div>
            <div class="mt-2 text-2xl font-bold text-yellow-700"><?= (int) $summary['stale'] ?></div>
            <div class="mt-1 text-xs text-slate-500">Intervalle : <?= (int) $supervisionInterval ?> min</div>
        </div>
    </div>

    <?php if (empty($servers)): ?>
        <p class="rounded border border-gray-200 bg-white p-4 text-gray-500">Aucun serveur a superviser pour le moment.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2 2xl:grid-cols-3">
            <?php foreach ($servers as $server):
                $lastCheckStatus = msmSupervisionLastCheck(
                    $server['last_check'] ?? null,
                    $server['last_check_age_seconds'] ?? null,
                    $supervisionInterval
                );
                $diskUsage = $diskUsages[(int) $server['id']] ?? null;
                $latency = $server['latency'] ?? null;
                $latencyMin = $server['latency_min_ms'] ?? null;
                $latencyMax = $server['latency_max_ms'] ?? null;
                $pingLoss = $server['ping_loss_percent'] ?? null;
                $packetsSent = $server['ping_packets_sent'] ?? null;
                $packetsReceived = $server['ping_packets_received'] ?? null;
            ?>
                <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($server['name'] ?? '') ?></h2>
                            <div class="text-sm text-slate-500"><?= htmlspecialchars($server['hostname'] ?? '') ?></div>
                        </div>
                        <a href="<?= $baseUrl ?>pages/details-cible.php?id=<?= (int) $server['id'] ?>"
                           class="shrink-0 text-sm font-semibold text-blue-700 hover:underline">
                            Details
                        </a>
                    </div>

                    <div class="mb-4 flex flex-wrap gap-2">
                        <?= msmSupervisionStatusBadge($server['status'] ?? null) ?>
                        <?= msmSupervisionSshBadge($server) ?>
                        <span class="inline-flex rounded border px-2 py-1 text-xs font-semibold <?= htmlspecialchars($lastCheckStatus['badge']) ?>"
                              title="<?= htmlspecialchars(msmDisplayDate($server['last_check'] ?? null)) ?>">
                            <?= htmlspecialchars($lastCheckStatus['text']) ?>
                        </span>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded border border-gray-200 bg-slate-50 p-3">
                            <dt class="text-xs font-semibold uppercase text-slate-500">Latence</dt>
                            <dd class="mt-1 font-semibold text-slate-900">
                                <?= $latency !== null ? (int) $latency . ' ms' : '-' ?>
                            </dd>
                            <?php if ($latencyMin !== null || $latencyMax !== null): ?>
                                <dd class="mt-1 text-xs text-slate-500">
                                    min/max : <?= $latencyMin !== null ? (int) $latencyMin : '-' ?> / <?= $latencyMax !== null ? (int) $latencyMax : '-' ?> ms
                                </dd>
                            <?php endif; ?>
                        </div>
                        <div class="rounded border border-gray-200 bg-slate-50 p-3">
                            <dt class="text-xs font-semibold uppercase text-slate-500">Perte ping</dt>
                            <dd class="mt-1"><?= msmSupervisionPingLossBadge($pingLoss) ?></dd>
                            <?php if ($packetsSent !== null || $packetsReceived !== null): ?>
                                <dd class="mt-1 text-xs text-slate-500">
                                    paquets : <?= $packetsReceived !== null ? (int) $packetsReceived : '-' ?> / <?= $packetsSent !== null ? (int) $packetsSent : '-' ?>
                                </dd>
                            <?php endif; ?>
                        </div>
                        <div class="rounded border border-gray-200 bg-slate-50 p-3">
                            <dt class="text-xs font-semibold uppercase text-slate-500">Dernier check</dt>
                            <dd class="mt-1 font-semibold text-slate-900"><?= htmlspecialchars($lastCheckStatus['detail']) ?></dd>
                        </div>
                        <div class="rounded border border-gray-200 bg-slate-50 p-3">
                            <dt class="text-xs font-semibold uppercase text-slate-500">Disque</dt>
                            <dd class="mt-1 font-semibold text-slate-900"><?= $diskUsage !== null ? (int) $diskUsage . ' %' : '-' ?></dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <?= msmSupervisionModuleBadge('Patch', !empty($server['patch_management_enabled']), 'border-blue-200 bg-blue-50 text-blue-700') ?>
                        <?= msmSupervisionModuleBadge('Secu', !empty($server['security_enabled']), 'border-green-200 bg-green-50 text-green-700') ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    const form = document.querySelector('form[action="update-status.php"]');
    if (form) {
        const button = form.querySelector('button');
        form.addEventListener('submit', () => {
            if (!button) return;
            button.disabled = true;
            button.innerText = 'Verification en cours...';
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
