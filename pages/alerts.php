<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/header.php';

use MSM\AlertRepository;

$repository = new AlertRepository($pdo);

$allowedStatuses = ['active', 'resolved', 'all'];
$allowedSeverities = ['', 'critical', 'warning', 'info'];
$allowedSources = ['', 'supervision', 'patch_management', 'os_lifecycle', 'security'];

$status = $_GET['status'] ?? 'active';
$severity = $_GET['severity'] ?? '';
$source = $_GET['source'] ?? '';

if (!in_array($status, $allowedStatuses, true)) {
    $status = 'active';
}
if (!in_array($severity, $allowedSeverities, true)) {
    $severity = '';
}
if (!in_array($source, $allowedSources, true)) {
    $source = '';
}

$alerts = $repository->getAlerts([
    'status' => $status,
    'severity' => $severity,
    'source' => $source,
]);
$counts = $repository->getActiveAlertCounts();

function msmAlertBadgeClass(string $severity): string
{
    return match ($severity) {
        'critical' => 'bg-red-100 text-red-700',
        'warning' => 'bg-yellow-100 text-yellow-800',
        default => 'bg-slate-100 text-slate-700',
    };
}

function msmAlertStatusClass(string $status): string
{
    return match ($status) {
        'active' => 'bg-red-100 text-red-700',
        'resolved' => 'bg-green-100 text-green-700',
        default => 'bg-slate-100 text-slate-700',
    };
}
?>

<div class="p-6">
    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Alertes</h1>
            <p class="mt-1 text-sm text-slate-600">
                Vue backoffice des alertes generees par <code>scripts/check-alerts.php</code>.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= $baseUrl ?>pages/alert-rules.php"
               class="inline-flex items-center gap-2 rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-gray-50">
                <i data-lucide="sliders-horizontal" class="h-4 w-4"></i>
                Regles
            </a>
            <a href="<?= $baseUrl ?>pages/alerts-wall.php" target="_blank"
               class="inline-flex items-center gap-2 rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                <i data-lucide="monitor" class="h-4 w-4"></i>
                Mur d'alertes
            </a>
        </div>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Actives</div>
            <div class="mt-2 text-3xl font-bold text-slate-900"><?= (int) ($counts['total'] ?? 0) ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Critiques</div>
            <div class="mt-2 text-3xl font-bold text-red-700"><?= (int) ($counts['critical'] ?? 0) ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Warnings</div>
            <div class="mt-2 text-3xl font-bold text-yellow-700"><?= (int) ($counts['warning'] ?? 0) ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Infos</div>
            <div class="mt-2 text-3xl font-bold text-slate-700"><?= (int) ($counts['info'] ?? 0) ?></div>
        </div>
    </div>

    <form method="get" class="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <label class="text-sm font-semibold text-slate-700">
                Statut
                <select name="status" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    <?php foreach (['active' => 'Actives', 'resolved' => 'Resolues', 'all' => 'Toutes'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Severite
                <select name="severity" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    <?php foreach (['' => 'Toutes', 'critical' => 'Critical', 'warning' => 'Warning', 'info' => 'Info'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $severity === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Source
                <select name="source" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    <?php foreach (['' => 'Toutes', 'supervision' => 'Supervision', 'patch_management' => 'Patch Management', 'os_lifecycle' => 'Cycle de vie OS', 'security' => 'Securite'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $source === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Filtrer
                </button>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Alerte</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Cible</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Source</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Statut</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Dates</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Occurrences</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (!$alerts): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                            Aucune alerte pour ces filtres.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($alerts as $alert): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 align-top">
                            <div class="flex flex-col gap-2">
                                <div class="font-semibold text-slate-900"><?= htmlspecialchars($alert['title']) ?></div>
                                <div class="text-sm text-slate-600"><?= htmlspecialchars($alert['message']) ?></div>
                                <span class="w-fit rounded px-2 py-1 text-xs font-semibold <?= msmAlertBadgeClass($alert['severity'] ?? 'info') ?>">
                                    <?= htmlspecialchars($alert['severity'] ?? 'info') ?>
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 align-top text-sm">
                            <?php if (!empty($alert['server_id'])): ?>
                                <a href="<?= $baseUrl ?>pages/details-cible.php?id=<?= (int) $alert['server_id'] ?>" class="font-semibold text-blue-700 hover:underline">
                                    <?= htmlspecialchars($alert['server_name'] ?? 'Cible inconnue') ?>
                                </a>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars($alert['hostname'] ?? '') ?></div>
                            <?php else: ?>
                                <span class="text-slate-500">Alerte globale</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 align-top text-sm">
                            <div class="font-semibold text-slate-700"><?= htmlspecialchars($alert['source'] ?? 'unknown') ?></div>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($alert['rule_key'] ?? '') ?></div>
                        </td>
                        <td class="px-4 py-3 align-top">
                            <span class="rounded px-2 py-1 text-xs font-semibold <?= msmAlertStatusClass($alert['status'] ?? 'unknown') ?>">
                                <?= htmlspecialchars($alert['status'] ?? 'unknown') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 align-top text-xs text-slate-600">
                            <div>Premiere : <?= htmlspecialchars($alert['first_seen_at'] ?? '') ?></div>
                            <div>Derniere : <?= htmlspecialchars($alert['last_seen_at'] ?? '') ?></div>
                            <?php if (!empty($alert['resolved_at'])): ?>
                                <div>Resolution : <?= htmlspecialchars($alert['resolved_at']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 align-top text-sm font-semibold text-slate-700">
                            <?= (int) ($alert['occurrence_count'] ?? 0) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
