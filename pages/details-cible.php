<?php
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/inventory_options.php';

use MSM\PatchStatusRepository;
use MSM\SettingsManager;

$settingsManager = new SettingsManager($pdo);
$targetTypes = msmInventoryOptions($settingsManager, 'target_types');
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
    return match ($status) {
        'ok' => '<span class="inline-flex rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">OK</span>',
        'warning' => '<span class="inline-flex rounded bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-700">Updates</span>',
        'critical' => '<span class="inline-flex rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">Critique</span>',
        'error' => '<span class="inline-flex rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">Erreur</span>',
        'unsupported' => '<span class="inline-flex rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">Non supporte</span>',
        default => '<span class="inline-flex rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">Jamais verifie</span>',
    };
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

$type = $server['target_type'] ?? 'other';
$environment = $server['environment'] ?? 'other';
$criticality = $server['criticality'] ?? 'medium';
$collectionMethod = $server['collection_method'] ?? 'manual';
$latency = $server['latency'] ?? null;
$diskUsage = msmDetailMetricValue($metrics, 'disk');
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

        <a href="<?= $baseUrl ?>pages/serveurs.php?edit=<?= (int) $server['id'] ?>"
           class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            <i data-lucide="pencil" class="w-4 h-4"></i>
            Modifier
        </a>
    </div>

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
                    <span class="text-sm font-semibold text-slate-800" title="<?= htmlspecialchars($server['last_check'] ?? '') ?>">
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
                        <dd class="font-semibold text-slate-900"><?= htmlspecialchars($latestPatchCheck['checked_at'] ?? '-') ?></dd>
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
