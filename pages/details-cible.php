<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/inventory_options.php';

use MSM\PatchStatusRepository;
use MSM\OsLifecycleRepository;
use MSM\ServerCheckHistoryRepository;
use MSM\SettingsManager;
use MSM\HardwareHealthRepository;
use MSM\HomeAssistantRepository;

$settingsManager = new SettingsManager($pdo);
$targetTypes = msmInventoryOptions($settingsManager, 'target_types');
$hardwareProfiles = msmHardwareProfiles();
$environments = msmInventoryOptions($settingsManager, 'environments');
$criticalities = msmInventoryOptions($settingsManager, 'criticalities');
$collectionMethods = msmInventoryOptions($settingsManager, 'collection_methods');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT *, TIMESTAMPDIFF(SECOND, last_check, NOW()) AS last_check_age_seconds
    FROM servers
    WHERE id = :id
");
$stmt->execute([':id' => $id]);
$server = $stmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';

if (!$server) {
    echo '<div class="rounded border border-red-200 bg-red-50 p-4 text-red-700 font-semibold">Cible introuvable.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$metricsStmt = $pdo->prepare("
    SELECT type, value, measured_at
    FROM server_metrics
    WHERE server_id = :id
    ORDER BY measured_at DESC
    LIMIT 10
");
$metricsStmt->execute([':id' => $id]);
$metrics = $metricsStmt->fetchAll(PDO::FETCH_ASSOC);

$patchRepository = new PatchStatusRepository($pdo);
$latestPatchCheck = $patchRepository->getLatestForServer((int) $server['id']);
$latestPatchUpdates = $latestPatchCheck ? $patchRepository->getUpdatesForCheck((int) $latestPatchCheck['id']) : [];

$osLifecycleRepository = new OsLifecycleRepository($pdo);
$latestOsLifecycleCheck = $osLifecycleRepository->getLatestForServer((int) $server['id']);

$hardwareHealthRepository = new HardwareHealthRepository($pdo);
$latestHardwareCheck = $hardwareHealthRepository->getLatestForServer((int) $server['id']);
$latestTemperatures = $latestHardwareCheck
    ? $hardwareHealthRepository->getTemperaturesForCheck((int) $latestHardwareCheck['id'])
    : [];
$latestSmartDisks = $latestHardwareCheck
    ? $hardwareHealthRepository->getSmartDisksForCheck((int) $latestHardwareCheck['id'])
    : [];

$latestHomeAssistantCheck = null;
if (($server['target_type'] ?? 'other') === 'home_assistant') {
    try {
        $homeAssistantChecksTableExists = (int) $pdo
            ->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'home_assistant_checks'")
            ->fetchColumn() > 0;

        if ($homeAssistantChecksTableExists) {
            $homeAssistantRepository = new HomeAssistantRepository($pdo);
            $latestHomeAssistantCheck = $homeAssistantRepository->getLatestForServer((int) $server['id']);
        }
    } catch (Throwable) {
        $latestHomeAssistantCheck = null;
    }
}

$checkHistoryRepository = new ServerCheckHistoryRepository($pdo);
$checkEvents = $checkHistoryRepository->latestForServer((int) $server['id'], 10);

function msmDetailStatusBadge(?string $status): string
{
    return match ($status) {
        'up' => '<span class="inline-flex items-center gap-1 rounded bg-green-100 px-2 py-1 text-sm font-semibold text-green-700"><i data-lucide="check-circle" class="w-4 h-4"></i>UP</span>',
        'down' => '<span class="inline-flex items-center gap-1 rounded bg-red-100 px-2 py-1 text-sm font-semibold text-red-700"><i data-lucide="x-circle" class="w-4 h-4"></i>DOWN</span>',
        default => '<span class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-1 text-sm font-semibold text-gray-600"><i data-lucide="help-circle" class="w-4 h-4"></i>Inconnu</span>',
    };
}

function msmDetailLastCheck(?string $lastCheck, mixed $ageSeconds): string
{
    if (empty($lastCheck)) {
        return 'Jamais';
    }

    if ($ageSeconds === null || !is_numeric($ageSeconds)) {
        return $lastCheck;
    }

    $seconds = (int) $ageSeconds;
    if ($seconds < 60) {
        return "a l'instant";
    }

    $minutes = (int) floor($seconds / 60);
    if ($minutes < 60) {
        return "il y a $minutes min";
    }

    $hours = (int) floor($minutes / 60);
    if ($hours < 24) {
        return "il y a $hours h";
    }

    return 'il y a ' . (int) floor($hours / 24) . ' j';
}

function msmDetailMetricValue(array $metrics, string $type): ?string
{
    foreach ($metrics as $metric) {
        if (($metric['type'] ?? '') === $type) {
            return (string) $metric['value'];
        }
    }

    return null;
}

function msmDetailPatchStatusBadge(?string $status): string
{
    return msmStatusBadge(msmStatusStateFromPatch($status), msmStatusLabelFromPatch($status));
}

function msmDetailPatchCollectorBadge(?string $collector): string
{
    if ($collector === null || $collector === '') {
        return '<span class="inline-flex rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-500">-</span>';
    }

    return '<span class="inline-flex rounded bg-blue-50 px-2 py-1 font-mono text-xs font-semibold text-blue-700">'
        . htmlspecialchars($collector)
        . '</span>';
}

function msmDetailOsLifecycleBadge(?string $status): string
{
    return msmStatusBadge(msmStatusStateFromOsLifecycle($status), msmStatusLabelFromOsLifecycle($status));
}

function msmDetailCheckEventBadge(?string $eventType): string
{
    return match ($eventType) {
        'ping_status' => '<span class="inline-flex rounded bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">Ping</span>',
        'ssh_status' => '<span class="inline-flex rounded bg-green-50 px-2 py-1 text-xs font-semibold text-green-700">SSH</span>',
        default => '<span class="inline-flex rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">Check</span>',
    };
}

function msmDetailOsLifecycleLabel(?array $check): string
{
    if (!$check) {
        return '-';
    }

    $prettyName = trim((string) ($check['os_pretty_name'] ?? ''));
    if ($prettyName !== '') {
        return $prettyName;
    }

    return trim((string) ($check['os_family'] ?? '') . ' ' . (string) ($check['os_version'] ?? '')) ?: '-';
}

function msmDetailPatchUpdatesTable(array $updates, string $type): string
{
    $filtered = array_values(array_filter(
        $updates,
        fn (array $update): bool => ($update['update_type'] ?? '') === $type
    ));

    if ($filtered === []) {
        return '<p class="text-sm italic text-slate-500">Aucun paquet.</p>';
    }

    $html = '<div class="overflow-x-auto rounded border border-gray-200">';
    $html .= '<table class="min-w-full text-sm">';
    $html .= '<thead class="bg-slate-100 text-left text-slate-600">';
    $html .= '<tr>';
    $html .= '<th class="px-3 py-2 font-semibold">Paquet</th>';
    $html .= '<th class="px-3 py-2 font-semibold">Version installee</th>';
    $html .= '<th class="px-3 py-2 font-semibold">Version candidate</th>';
    $html .= '<th class="px-3 py-2 font-semibold">Source</th>';
    $html .= '</tr>';
    $html .= '</thead><tbody class="divide-y divide-gray-200">';

    foreach ($filtered as $update) {
        $html .= '<tr>';
        $html .= '<td class="px-3 py-2 font-mono font-semibold text-slate-900">' . htmlspecialchars($update['package_name'] ?? '-') . '</td>';
        $html .= '<td class="px-3 py-2 text-slate-600">' . htmlspecialchars($update['installed_version'] ?: '-') . '</td>';
        $html .= '<td class="px-3 py-2 text-slate-900">' . htmlspecialchars($update['candidate_version'] ?: '-') . '</td>';
        $html .= '<td class="px-3 py-2 text-slate-600">' . htmlspecialchars($update['source'] ?: '-') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    return $html;
}

function msmDetailBytes(?string $bytes): string
{
    if ($bytes === null || !is_numeric($bytes)) {
        return '-';
    }

    $value = (float) $bytes;
    $units = ['o', 'Kio', 'Mio', 'Gio', 'Tio', 'Pio'];
    $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }

    return number_format($value, $unit === 0 ? 0 : 1, ',', ' ') . ' ' . $units[$unit];
}

function msmDetailSmartBadge(mixed $passed): string
{
    if ($passed === null) {
        return '<span class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">Inconnu</span>';
    }

    return (int) $passed === 1
        ? '<span class="rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">OK</span>'
        : '<span class="rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">ECHEC</span>';
}

function msmDetailHomeAssistantStatusBadge(?string $status): string
{
    return match ($status) {
        'ok' => '<span class="inline-flex rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">OK</span>',
        'warning' => '<span class="inline-flex rounded bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-800">Update</span>',
        'error' => '<span class="inline-flex rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">Erreur</span>',
        'unsupported' => '<span class="inline-flex rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">Limite</span>',
        default => '<span class="inline-flex rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">Inconnu</span>',
    };
}

$type = $server['target_type'] ?? 'other';
$hardwareProfile = $server['hardware_profile'] ?? 'unknown';
$environment = $server['environment'] ?? 'other';
$criticality = $server['criticality'] ?? 'medium';
$collectionMethod = $server['collection_method'] ?? 'manual';
$latency = $server['latency'] ?? null;
$latencyMin = $server['latency_min_ms'] ?? null;
$latencyMax = $server['latency_max_ms'] ?? null;
$pingLoss = $server['ping_loss_percent'] ?? null;
$pingPacketsSent = $server['ping_packets_sent'] ?? null;
$pingPacketsReceived = $server['ping_packets_received'] ?? null;
$diskUsage = msmDetailMetricValue($metrics, 'disk');
$canRefreshSupervision = $authManager->userCan('supervision');
$canRefreshPatch = $authManager->userCan('patch_management') && !empty($server['patch_management_enabled']);
$canRefreshOsLifecycle = $authManager->userCan('patch_management')
    && !empty($server['ssh_enabled'])
    && in_array($type, ['linux', 'proxmox'], true);
$canRefreshSecurity = $authManager->userCan('securite') && !empty($server['security_enabled']) && !empty($server['ssh_enabled']);
$showHardwareHealthCard = msmHardwareProfileSupportsSensors($hardwareProfile);
$canRefreshHardware = $authManager->userCan('supervision')
    && $showHardwareHealthCard
    && !empty($server['ssh_enabled']);
$canRefreshHomeAssistant = $authManager->userCan('supervision')
    && $type === 'home_assistant'
    && !empty($server['ssh_enabled']);
?>

<div class="p-6">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <a href="<?= $baseUrl ?>pages/serveurs.php"
               class="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:underline">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Retour aux serveurs
            </a>
            <h1 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($server['name'] ?? '') ?></h1>
            <p class="text-sm text-slate-600"><?= htmlspecialchars($server['hostname'] ?? '') ?></p>
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            <?php if ($canRefreshSupervision): ?>
                <form method="post" action="<?= $baseUrl ?>pages/refresh-target.php">
                    <?= msmCsrfField() ?>
                    <input type="hidden" name="server_id" value="<?= (int) $server['id'] ?>">
                    <input type="hidden" name="module" value="supervision">
                    <button type="submit" class="inline-flex items-center gap-2 rounded border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="activity" class="w-4 h-4"></i>
                        Supervision
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($canRefreshPatch): ?>
                <form method="post" action="<?= $baseUrl ?>pages/refresh-target.php">
                    <?= msmCsrfField() ?>
                    <input type="hidden" name="server_id" value="<?= (int) $server['id'] ?>">
                    <input type="hidden" name="module" value="patch_management">
                    <button type="submit" class="inline-flex items-center gap-2 rounded border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="package-check" class="w-4 h-4"></i>
                        Patch
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($canRefreshOsLifecycle): ?>
                <form method="post" action="<?= $baseUrl ?>pages/refresh-target.php">
                    <?= msmCsrfField() ?>
                    <input type="hidden" name="server_id" value="<?= (int) $server['id'] ?>">
                    <input type="hidden" name="module" value="os_lifecycle">
                    <button type="submit" class="inline-flex items-center gap-2 rounded border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        Cycle OS
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($canRefreshSecurity): ?>
                <form method="post" action="<?= $baseUrl ?>pages/refresh-target.php">
                    <?= msmCsrfField() ?>
                    <input type="hidden" name="server_id" value="<?= (int) $server['id'] ?>">
                    <input type="hidden" name="module" value="security">
                    <button type="submit" class="inline-flex items-center gap-2 rounded border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>
                        Securite
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($canRefreshHardware): ?>
                <form method="post" action="<?= $baseUrl ?>pages/refresh-target.php">
                    <?= msmCsrfField() ?>
                    <input type="hidden" name="server_id" value="<?= (int) $server['id'] ?>">
                    <input type="hidden" name="module" value="hardware_health">
                    <button type="submit" class="inline-flex items-center gap-2 rounded border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="thermometer" class="w-4 h-4"></i>
                        Temperature
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($canRefreshHomeAssistant): ?>
                <form method="post" action="<?= $baseUrl ?>pages/refresh-target.php">
                    <?= msmCsrfField() ?>
                    <input type="hidden" name="server_id" value="<?= (int) $server['id'] ?>">
                    <input type="hidden" name="module" value="home_assistant">
                    <button type="submit" class="inline-flex items-center gap-2 rounded border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="house" class="w-4 h-4"></i>
                        Home Assistant
                    </button>
                </form>
            <?php endif; ?>

            <a href="<?= $baseUrl ?>pages/serveurs.php?edit=<?= (int) $server['id'] ?>"
               class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                <i data-lucide="pencil" class="w-4 h-4"></i>
                Modifier
            </a>
        </div>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="mb-4 flex items-center rounded border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            <i data-lucide="alert-circle" class="mr-2 h-5 w-5"></i>
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="mb-4 flex items-center rounded border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
            <i data-lucide="check-circle" class="mr-2 h-5 w-5"></i>
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase text-slate-500">Etat actuel</h2>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-600">Statut</span>
                    <?= msmDetailStatusBadge($server['status'] ?? null) ?>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-600">SSH</span>
                    <?php if (empty($server['ssh_enabled'])): ?>
                        <span class="rounded bg-gray-100 px-2 py-1 text-sm font-semibold text-gray-500">Desactive</span>
                    <?php elseif (($server['ssh_status'] ?? '') === 'success'): ?>
                        <span class="rounded bg-green-100 px-2 py-1 text-sm font-semibold text-green-700">OK</span>
                    <?php else: ?>
                        <span class="rounded bg-red-100 px-2 py-1 text-sm font-semibold text-red-700">Echec</span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-600">Dernier check</span>
                    <span class="text-sm font-semibold text-slate-800" title="<?= htmlspecialchars(msmDisplayDate($server['last_check'] ?? null)) ?>">
                        <?= htmlspecialchars(msmDetailLastCheck($server['last_check'] ?? null, $server['last_check_age_seconds'] ?? null)) ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase text-slate-500">Metriques connues</h2>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <div class="text-xs text-slate-500">Latence</div>
                    <div class="text-lg font-bold text-slate-900"><?= $latency !== null ? (int) $latency . ' ms' : '-' ?></div>
                    <?php if ($latencyMin !== null || $latencyMax !== null): ?>
                        <div class="text-xs text-slate-500">
                            min/max : <?= $latencyMin !== null ? (int) $latencyMin : '-' ?> / <?= $latencyMax !== null ? (int) $latencyMax : '-' ?> ms
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Perte ping</div>
                    <div class="text-lg font-bold <?= $pingLoss !== null && (float) $pingLoss > 0 ? 'text-yellow-700' : 'text-slate-900' ?>">
                        <?= $pingLoss !== null ? htmlspecialchars(number_format((float) $pingLoss, 1, ',', '')) . ' %' : '-' ?>
                    </div>
                    <?php if ($pingPacketsSent !== null || $pingPacketsReceived !== null): ?>
                        <div class="text-xs text-slate-500">
                            paquets : <?= $pingPacketsReceived !== null ? (int) $pingPacketsReceived : '-' ?> / <?= $pingPacketsSent !== null ? (int) $pingPacketsSent : '-' ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Disque</div>
                    <div class="text-lg font-bold text-slate-900"><?= $diskUsage !== null ? round((float) $diskUsage) . ' %' : '-' ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">OS</div>
                    <div class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($server['os'] ?? 'OS inconnu') ?></div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Collecte</div>
                    <div class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($collectionMethods[$collectionMethod] ?? $collectionMethod) ?></div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase text-slate-500">Prometheus</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">server</dt>
                    <dd class="font-mono text-slate-900"><?= htmlspecialchars($server['name'] ?? '') ?></dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">hostname</dt>
                    <dd class="font-mono text-slate-900"><?= htmlspecialchars($server['hostname'] ?? '') ?></dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">type</dt>
                    <dd class="font-mono text-slate-900"><?= htmlspecialchars($type) ?></dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Inventaire</h2>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-slate-500">Type de cible</dt>
                    <dd class="font-semibold text-slate-900"><?= htmlspecialchars($targetTypes[$type] ?? $type) ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Environnement</dt>
                    <dd class="font-semibold text-slate-900"><?= htmlspecialchars($environments[$environment] ?? $environment) ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Profil materiel</dt>
                    <dd class="font-semibold text-slate-900"><?= htmlspecialchars($hardwareProfiles[$hardwareProfile] ?? $hardwareProfiles['unknown']) ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Criticite</dt>
                    <dd class="font-semibold text-slate-900"><?= htmlspecialchars($criticalities[$criticality] ?? $criticality) ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Methode de collecte</dt>
                    <dd class="font-semibold text-slate-900"><?= htmlspecialchars($collectionMethods[$collectionMethod] ?? $collectionMethod) ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Analyse securite</dt>
                    <dd class="font-semibold <?= !empty($server['security_enabled']) ? 'text-green-700' : 'text-slate-500' ?>">
                        <?= !empty($server['security_enabled']) ? 'Activee' : 'Desactivee' ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Patch management</dt>
                    <dd class="font-semibold <?= !empty($server['patch_management_enabled']) ? 'text-green-700' : 'text-slate-500' ?>">
                        <?= !empty($server['patch_management_enabled']) ? 'Active' : 'Desactive' ?>
                    </dd>
                </div>
                <div class="md:col-span-2">
                    <dt class="mb-2 text-slate-500">Tags</dt>
                    <dd class="flex flex-wrap gap-2">
                        <?php $tags = msmInventoryTags($server['tags'] ?? null); ?>
                        <?php if ($tags): ?>
                            <?php foreach ($tags as $tag): ?>
                                <span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">
                                    <?= htmlspecialchars($tag) ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-slate-400">Aucun tag</span>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Patch Management</h2>
            <?php if (empty($server['patch_management_enabled'])): ?>
                <p class="text-sm italic text-slate-500">Module desactive pour cette cible.</p>
            <?php elseif (!$latestPatchCheck): ?>
                <p class="text-sm italic text-slate-500">Aucun check patch management enregistre.</p>
            <?php else: ?>
                <?php if (!empty($latestPatchCheck['reboot_required'])): ?>
                    <div class="mb-4 flex items-center gap-2 rounded border border-orange-200 bg-orange-50 px-3 py-2 text-sm font-semibold text-orange-800">
                        <i data-lucide="rotate-cw" class="w-4 h-4"></i>
                        Reboot requis sur cette cible
                    </div>
                <?php endif; ?>

                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500">Statut</dt>
                        <dd class="mt-1"><?= msmDetailPatchStatusBadge($latestPatchCheck['status'] ?? null) ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Collecteur</dt>
                        <dd class="mt-1"><?= msmDetailPatchCollectorBadge($latestPatchCheck['collector'] ?? null) ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Dernier check</dt>
                        <dd class="font-semibold text-slate-900"><?= htmlspecialchars(msmDisplayDate($latestPatchCheck['checked_at'] ?? null, '-')) ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Updates securite</dt>
                        <dd class="font-bold text-red-700"><?= (int) ($latestPatchCheck['security_updates_count'] ?? 0) ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Updates normales</dt>
                        <dd class="font-bold text-slate-900"><?= (int) ($latestPatchCheck['normal_updates_count'] ?? 0) ?></dd>
                    </div>
                </dl>

                <?php if (!empty($latestPatchCheck['error_message'])): ?>
                    <p class="mt-4 rounded bg-red-50 px-3 py-2 text-sm text-red-700">
                        <?= htmlspecialchars($latestPatchCheck['error_message']) ?>
                    </p>
                <?php endif; ?>

                <?php if ($latestPatchUpdates): ?>
                    <div class="mt-5 space-y-4">
                        <div>
                            <h3 class="mb-2 text-sm font-semibold text-red-700">
                                Paquets securite (<?= (int) ($latestPatchCheck['security_updates_count'] ?? 0) ?>)
                            </h3>
                            <?= msmDetailPatchUpdatesTable($latestPatchUpdates, 'security') ?>
                        </div>

                        <div>
                            <h3 class="mb-2 text-sm font-semibold text-slate-800">
                                Paquets normaux (<?= (int) ($latestPatchCheck['normal_updates_count'] ?? 0) ?>)
                            </h3>
                            <?= msmDetailPatchUpdatesTable($latestPatchUpdates, 'normal') ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Cycle de vie OS</h2>
            <?php if (!$latestOsLifecycleCheck): ?>
                <p class="text-sm italic text-slate-500">Aucun check de cycle de vie OS enregistre.</p>
            <?php else: ?>
                <?php if (!empty($latestOsLifecycleCheck['upgrade_available'])): ?>
                    <div class="mb-4 flex items-center gap-2 rounded border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800">
                        <i data-lucide="arrow-up-circle" class="w-4 h-4"></i>
                        Upgrade disponible vers <?= htmlspecialchars($latestOsLifecycleCheck['upgrade_target_label'] ?: $latestOsLifecycleCheck['upgrade_target_version']) ?>
                    </div>
                <?php endif; ?>

                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500">Statut support</dt>
                        <dd class="mt-1"><?= msmDetailOsLifecycleBadge($latestOsLifecycleCheck['support_status'] ?? null) ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Dernier check</dt>
                        <dd class="font-semibold text-slate-900"><?= htmlspecialchars(msmDisplayDate($latestOsLifecycleCheck['checked_at'] ?? null, '-')) ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">OS detecte</dt>
                        <dd class="font-semibold text-slate-900"><?= htmlspecialchars(msmDetailOsLifecycleLabel($latestOsLifecycleCheck)) ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Fin de support connue</dt>
                        <dd class="font-semibold text-slate-900"><?= htmlspecialchars($latestOsLifecycleCheck['support_ends_at'] ?? '-') ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Famille</dt>
                        <dd class="font-mono text-slate-900"><?= htmlspecialchars($latestOsLifecycleCheck['os_family'] ?? '-') ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Version</dt>
                        <dd class="font-mono text-slate-900"><?= htmlspecialchars($latestOsLifecycleCheck['os_version'] ?? '-') ?></dd>
                    </div>
                </dl>

                <?php if (!empty($latestOsLifecycleCheck['error_message'])): ?>
                    <p class="mt-4 rounded bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
                        <?= htmlspecialchars($latestOsLifecycleCheck['error_message']) ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($showHardwareHealthCard): ?>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">Sante materielle</h2>
                <?php if ($latestHardwareCheck && $latestHardwareCheck['max_temperature_celsius'] !== null): ?>
                    <span class="inline-flex items-center gap-1 rounded bg-orange-50 px-2 py-1 text-sm font-bold text-orange-700">
                        <i data-lucide="thermometer" class="w-4 h-4"></i>
                        <?= htmlspecialchars(number_format((float) $latestHardwareCheck['max_temperature_celsius'], 1, ',', '')) ?> &deg;C max
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!$latestHardwareCheck): ?>
                <p class="text-sm italic text-slate-500">Aucun check materiel enregistre.</p>
            <?php else: ?>
                <dl class="mb-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
                    <div>
                        <dt class="text-slate-500">Statut</dt>
                        <dd class="font-semibold text-slate-900"><?= htmlspecialchars($latestHardwareCheck['status'] ?? 'unknown') ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Collecteur</dt>
                        <dd class="font-mono text-slate-900"><?= htmlspecialchars($latestHardwareCheck['collector'] ?: '-') ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Dernier check</dt>
                        <dd class="font-semibold text-slate-900"><?= htmlspecialchars(msmDisplayDate($latestHardwareCheck['checked_at'] ?? null, '-')) ?></dd>
                    </div>
                </dl>

                <?php if (!empty($latestHardwareCheck['error_message'])): ?>
                    <p class="mb-4 rounded bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
                        <?= htmlspecialchars($latestHardwareCheck['error_message']) ?>
                    </p>
                <?php endif; ?>

                <?php if ($latestTemperatures): ?>
                    <div class="overflow-x-auto rounded border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-100 text-left text-slate-600">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Sonde</th>
                                    <th class="px-3 py-2 font-semibold">Type</th>
                                    <th class="px-3 py-2 text-right font-semibold">Temperature</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($latestTemperatures as $temperature): ?>
                                    <tr>
                                        <td class="px-3 py-2 font-semibold text-slate-900"><?= htmlspecialchars($temperature['sensor_label']) ?></td>
                                        <td class="px-3 py-2 text-slate-500"><?= htmlspecialchars($temperature['sensor_type']) ?></td>
                                        <td class="px-3 py-2 text-right font-bold text-orange-700">
                                            <?= htmlspecialchars(number_format((float) $temperature['temperature_celsius'], 1, ',', '')) ?> &deg;C
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-sm italic text-slate-500">Aucune temperature disponible.</p>
                <?php endif; ?>

                <?php if ($hardwareProfile === 'physical'): ?>
                    <div class="mt-5 border-t border-gray-200 pt-4">
                        <h3 class="mb-3 text-sm font-semibold uppercase text-slate-500">Disques SMART</h3>

                        <?php if (!empty($latestHardwareCheck['smart_error_message'])): ?>
                            <p class="mb-3 rounded bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
                                <?= htmlspecialchars($latestHardwareCheck['smart_error_message']) ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($latestSmartDisks): ?>
                            <div class="overflow-x-auto rounded border border-gray-200">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-100 text-left text-slate-600">
                                        <tr>
                                            <th class="px-3 py-2 font-semibold">Disque</th>
                                            <th class="px-3 py-2 font-semibold">Modele</th>
                                            <th class="px-3 py-2 font-semibold">Capacite</th>
                                            <th class="px-3 py-2 font-semibold">SMART</th>
                                            <th class="px-3 py-2 font-semibold">Temperature</th>
                                            <th class="px-3 py-2 font-semibold">Heures</th>
                                            <th class="px-3 py-2 font-semibold">Usure</th>
                                            <th class="px-3 py-2 font-semibold">Erreurs media</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($latestSmartDisks as $disk): ?>
                                            <tr>
                                                <td class="px-3 py-2">
                                                    <div class="font-mono font-semibold text-slate-900"><?= htmlspecialchars($disk['device_name']) ?></div>
                                                    <div class="text-xs text-slate-500"><?= htmlspecialchars($disk['protocol'] ?: ($disk['device_type'] ?: '-')) ?></div>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <div class="font-semibold text-slate-900"><?= htmlspecialchars($disk['model_name'] ?: '-') ?></div>
                                                    <?php if (!empty($disk['serial_number'])): ?>
                                                        <div class="text-xs text-slate-500">S/N <?= htmlspecialchars($disk['serial_number']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-3 py-2"><?= htmlspecialchars(msmDetailBytes($disk['capacity_bytes'])) ?></td>
                                                <td class="px-3 py-2"><?= msmDetailSmartBadge($disk['smart_passed']) ?></td>
                                                <td class="px-3 py-2">
                                                    <?= $disk['temperature_celsius'] !== null
                                                        ? htmlspecialchars(number_format((float) $disk['temperature_celsius'], 1, ',', '')) . ' &deg;C'
                                                        : '-' ?>
                                                </td>
                                                <td class="px-3 py-2"><?= $disk['power_on_hours'] !== null ? number_format((int) $disk['power_on_hours'], 0, ',', ' ') . ' h' : '-' ?></td>
                                                <td class="px-3 py-2"><?= $disk['percentage_used'] !== null ? htmlspecialchars(number_format((float) $disk['percentage_used'], 1, ',', '')) . ' %' : '-' ?></td>
                                                <td class="px-3 py-2"><?= $disk['media_errors'] !== null ? number_format((int) $disk['media_errors'], 0, ',', ' ') : '-' ?></td>
                                            </tr>
                                            <?php if (!empty($disk['error_message'])): ?>
                                                <tr class="bg-yellow-50">
                                                    <td colspan="8" class="px-3 py-2 text-xs text-yellow-800"><?= htmlspecialchars($disk['error_message']) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif (empty($latestHardwareCheck['smart_error_message'])): ?>
                            <p class="text-sm italic text-slate-500">Aucun disque SMART detecte.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($type === 'home_assistant'): ?>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-900">Home Assistant</h2>
                    <?php if ($latestHomeAssistantCheck): ?>
                        <?= msmDetailHomeAssistantStatusBadge($latestHomeAssistantCheck['status'] ?? null) ?>
                    <?php endif; ?>
                </div>

                <?php if (!$latestHomeAssistantCheck): ?>
                    <p class="text-sm italic text-slate-500">Aucun check Home Assistant enregistre.</p>
                <?php else: ?>
                    <dl class="mb-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
                        <div>
                            <dt class="text-slate-500">Installation</dt>
                            <dd class="font-semibold text-slate-900"><?= htmlspecialchars($latestHomeAssistantCheck['installation_type'] ?: '-') ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Collecteur</dt>
                            <dd class="font-mono text-slate-900"><?= htmlspecialchars($latestHomeAssistantCheck['collector'] ?: '-') ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Dernier check</dt>
                            <dd class="font-semibold text-slate-900"><?= htmlspecialchars(msmDisplayDate($latestHomeAssistantCheck['checked_at'] ?? null, '-')) ?></dd>
                        </div>
                    </dl>

                    <?php if (!empty($latestHomeAssistantCheck['error_message'])): ?>
                        <p class="mb-4 rounded bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
                            <?= htmlspecialchars($latestHomeAssistantCheck['error_message']) ?>
                        </p>
                    <?php endif; ?>

                    <div class="overflow-x-auto rounded border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-100 text-left text-slate-600">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Composant</th>
                                    <th class="px-3 py-2 font-semibold">Version</th>
                                    <th class="px-3 py-2 font-semibold">Derniere version</th>
                                    <th class="px-3 py-2 font-semibold">Update</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ([
                                    'Home Assistant' => ['version' => 'ha_version', 'latest' => 'ha_latest_version', 'update' => 'ha_update_available'],
                                    'Supervisor' => ['version' => 'supervisor_version', 'latest' => 'supervisor_latest_version', 'update' => 'supervisor_update_available'],
                                    'OS' => ['version' => 'os_version', 'latest' => 'os_latest_version', 'update' => 'os_update_available'],
                                ] as $label => $fields): ?>
                                    <tr>
                                        <td class="px-3 py-2 font-semibold text-slate-900"><?= htmlspecialchars($label) ?></td>
                                        <td class="px-3 py-2"><?= htmlspecialchars($latestHomeAssistantCheck[$fields['version']] ?: '-') ?></td>
                                        <td class="px-3 py-2"><?= htmlspecialchars($latestHomeAssistantCheck[$fields['latest']] ?: '-') ?></td>
                                        <td class="px-3 py-2">
                                            <?php if ($latestHomeAssistantCheck[$fields['update']] === null): ?>
                                                <span class="text-slate-500">-</span>
                                            <?php elseif ((int) $latestHomeAssistantCheck[$fields['update']] === 1): ?>
                                                <span class="rounded bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-800">Oui</span>
                                            <?php else: ?>
                                                <span class="rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">Non</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <dl class="mt-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                        <div>
                            <dt class="text-slate-500">OS hote</dt>
                            <dd class="font-semibold text-slate-900"><?= htmlspecialchars($latestHomeAssistantCheck['host_os'] ?: '-') ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Kernel</dt>
                            <dd class="font-mono text-slate-900"><?= htmlspecialchars($latestHomeAssistantCheck['kernel'] ?: '-') ?></dd>
                        </div>
                    </dl>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Historique supervision</h2>
            <?php if (!$checkEvents): ?>
                <p class="text-sm italic text-slate-500">Aucun changement de statut enregistre.</p>
            <?php else: ?>
                <div class="overflow-x-auto rounded border border-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-100 text-left text-slate-600">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Type</th>
                                <th class="px-3 py-2 font-semibold">Changement</th>
                                <th class="px-3 py-2 font-semibold">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($checkEvents as $event): ?>
                                <tr>
                                    <td class="px-3 py-2"><?= msmDetailCheckEventBadge($event['event_type'] ?? null) ?></td>
                                    <td class="px-3 py-2">
                                        <div class="font-semibold text-slate-900">
                                            <?= htmlspecialchars(($event['previous_value'] ?? '-') . ' -> ' . ($event['new_value'] ?? '-')) ?>
                                        </div>
                                        <?php if (!empty($event['message'])): ?>
                                            <div class="text-xs text-slate-500"><?= htmlspecialchars($event['message']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 text-slate-500"><?= htmlspecialchars(msmDisplayDate($event['created_at'] ?? null, '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Dernieres mesures</h2>
            <?php if (!$metrics): ?>
                <p class="text-sm italic text-slate-500">Aucune mesure enregistree pour cette cible.</p>
            <?php else: ?>
                <table class="w-full text-sm">
                    <thead class="border-b text-left text-slate-500">
                        <tr>
                            <th class="py-2">Type</th>
                            <th class="py-2">Valeur</th>
                            <th class="py-2">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($metrics as $metric): ?>
                            <tr class="border-b last:border-0">
                                <td class="py-2 font-semibold text-slate-800"><?= htmlspecialchars($metric['type']) ?></td>
                                <td class="py-2"><?= htmlspecialchars((string) $metric['value']) ?></td>
                                <td class="py-2 text-slate-500"><?= htmlspecialchars($metric['measured_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
