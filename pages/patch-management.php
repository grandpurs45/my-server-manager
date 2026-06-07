<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/inventory_options.php';

use MSM\PatchStatusRepository;
use MSM\SettingsManager;

$settingsManager = new SettingsManager($pdo);
$targetTypes = msmInventoryOptions($settingsManager, 'target_types');
$environments = msmInventoryOptions($settingsManager, 'environments');
$criticalities = msmInventoryOptions($settingsManager, 'criticalities');

$repository = new PatchStatusRepository($pdo);
$targets = $repository->getOverview();
$disabledCount = $repository->countDisabledTargets();

$filters = [
    'status' => $_GET['status'] ?? '',
    'type' => $_GET['type'] ?? '',
    'action' => $_GET['action'] ?? '',
];

function msmPatchNeedsAction(array $target): bool
{
    return (int) ($target['security_updates_count'] ?? 0) > 0
        || !empty($target['reboot_required'])
        || !empty($target['os_upgrade_available'])
        || in_array($target['os_support_status'] ?? null, ['eol', 'eol_soon'], true)
        || in_array($target['patch_status'] ?? null, ['critical', 'error'], true);
}

function msmPatchPriority(array $target): int
{
    if (($target['patch_status'] ?? null) === 'error') {
        return 0;
    }

    if (in_array($target['os_support_status'] ?? null, ['eol', 'eol_soon'], true)) {
        return 1;
    }

    if ((int) ($target['security_updates_count'] ?? 0) > 0) {
        return 2;
    }

    if (!empty($target['reboot_required'])) {
        return 3;
    }

    if (!empty($target['os_upgrade_available'])) {
        return 4;
    }

    if (($target['patch_status'] ?? null) === 'warning') {
        return 5;
    }

    return 9;
}

$targets = array_values(array_filter($targets, function (array $target) use ($filters): bool {
    if ($filters['status'] !== '' && ($target['patch_status'] ?? 'never') !== $filters['status']) {
        return false;
    }

    if ($filters['type'] !== '' && ($target['target_type'] ?? 'other') !== $filters['type']) {
        return false;
    }

    return match ($filters['action']) {
        'needs_action' => msmPatchNeedsAction($target),
        'security' => (int) ($target['security_updates_count'] ?? 0) > 0,
        'reboot' => !empty($target['reboot_required']),
        'os_upgrade' => !empty($target['os_upgrade_available']),
        'os_risk' => in_array($target['os_support_status'] ?? null, ['eol', 'eol_soon'], true),
        'errors' => ($target['patch_status'] ?? null) === 'error',
        default => true,
    };
}));

usort($targets, function (array $a, array $b): int {
    return msmPatchPriority($a) <=> msmPatchPriority($b)
        ?: strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
});

$needsActionCount = count(array_filter($targets, fn (array $target): bool => msmPatchNeedsAction($target)));
$securityUpdatesCount = array_sum(array_map(fn ($target) => (int) ($target['security_updates_count'] ?? 0), $targets));
$rebootRequiredCount = array_sum(array_map(fn ($target) => (int) ($target['reboot_required'] ?? 0), $targets));
$osUpgradeCount = array_sum(array_map(fn ($target) => (int) ($target['os_upgrade_available'] ?? 0), $targets));
$osRiskCount = count(array_filter($targets, fn (array $target): bool => in_array($target['os_support_status'] ?? null, ['eol', 'eol_soon'], true)));

function msmPatchStatusBadge(?string $status): string
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

function msmPatchFormatDate(?string $date): string
{
    return $date ? htmlspecialchars($date) : '-';
}

function msmPatchRebootBadge(bool $rebootRequired): string
{
    if ($rebootRequired) {
        return '<span class="inline-flex items-center gap-1 rounded bg-orange-100 px-2 py-1 text-xs font-semibold text-orange-800"><i data-lucide="rotate-cw" class="w-3 h-3"></i>Reboot requis</span>';
    }

    return '<span class="inline-flex rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">Non</span>';
}

function msmPatchCollectorBadge(?string $collector): string
{
    if ($collector === null || $collector === '') {
        return '<span class="inline-flex rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-500">-</span>';
    }

    return '<span class="inline-flex rounded bg-blue-50 px-2 py-1 font-mono text-xs font-semibold text-blue-700">'
        . htmlspecialchars($collector)
        . '</span>';
}

function msmPatchOsLifecycleBadge(?string $status): string
{
    return match ($status) {
        'supported' => '<span class="inline-flex rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">Support actif</span>',
        'eol_soon' => '<span class="inline-flex rounded bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-700">Fin proche</span>',
        'eol' => '<span class="inline-flex rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">Obsolete</span>',
        default => '<span class="inline-flex rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">Inconnu</span>',
    };
}

function msmPatchActionBadges(array $target): string
{
    $badges = [];

    if ((int) ($target['security_updates_count'] ?? 0) > 0) {
        $badges[] = '<span class="inline-flex rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">Securite</span>';
    }

    if (!empty($target['reboot_required'])) {
        $badges[] = '<span class="inline-flex rounded bg-orange-100 px-2 py-1 text-xs font-semibold text-orange-800">Reboot</span>';
    }

    if (!empty($target['os_upgrade_available'])) {
        $badges[] = '<span class="inline-flex rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700">Upgrade OS</span>';
    }

    if (in_array($target['os_support_status'] ?? null, ['eol', 'eol_soon'], true)) {
        $badges[] = '<span class="inline-flex rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">Support OS</span>';
    }

    if (($target['patch_status'] ?? null) === 'error') {
        $badges[] = '<span class="inline-flex rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">Erreur check</span>';
    }

    if (!$badges) {
        return '<span class="text-xs text-slate-400">-</span>';
    }

    return '<div class="flex flex-wrap gap-1">' . implode('', $badges) . '</div>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Patch Management</h1>
        <p class="mt-1 text-sm text-slate-600">
            Derniers resultats connus des checks de mises a jour. Aucun check lourd n'est lance depuis cette page.
        </p>
    </div>

    <div class="mb-4 grid grid-cols-1 md:grid-cols-5 gap-3">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Cibles affichees</div>
            <div class="mt-1 text-2xl font-bold text-slate-900"><?= count($targets) ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">A traiter</div>
            <div class="mt-1 text-2xl font-bold text-red-700"><?= $needsActionCount ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Updates securite</div>
            <div class="mt-1 text-2xl font-bold text-red-700"><?= $securityUpdatesCount ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Reboots requis</div>
            <div class="mt-1 text-2xl font-bold text-yellow-700"><?= $rebootRequiredCount ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Upgrades OS</div>
            <div class="mt-1 text-2xl font-bold text-blue-700"><?= $osUpgradeCount ?></div>
            <?php if ($osRiskCount > 0): ?>
                <div class="mt-1 text-xs font-semibold text-red-700"><?= $osRiskCount ?> support OS a risque</div>
            <?php endif; ?>
        </div>
    </div>

    <form method="get" class="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Action</span>
                <select name="action" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Toutes</option>
                    <option value="needs_action" <?= $filters['action'] === 'needs_action' ? 'selected' : '' ?>>A traiter</option>
                    <option value="security" <?= $filters['action'] === 'security' ? 'selected' : '' ?>>Updates securite</option>
                    <option value="reboot" <?= $filters['action'] === 'reboot' ? 'selected' : '' ?>>Reboot requis</option>
                    <option value="os_upgrade" <?= $filters['action'] === 'os_upgrade' ? 'selected' : '' ?>>Upgrade OS disponible</option>
                    <option value="os_risk" <?= $filters['action'] === 'os_risk' ? 'selected' : '' ?>>Support OS a risque</option>
                    <option value="errors" <?= $filters['action'] === 'errors' ? 'selected' : '' ?>>Erreurs de check</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Statut patch</span>
                <select name="status" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Tous</option>
                    <?php foreach (['ok', 'warning', 'critical', 'error', 'unsupported'] as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                            <?= htmlspecialchars($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase text-slate-500">Type</span>
                <select name="type" class="w-full rounded border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Tous</option>
                    <?php foreach ($targetTypes as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= $filters['type'] === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    Filtrer
                </button>
                <a href="<?= $baseUrl ?>pages/patch-management.php"
                   class="inline-flex items-center gap-2 rounded border border-gray-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-gray-50">
                    <i data-lucide="x" class="w-4 h-4"></i>
                    Reset
                </a>
            </div>
        </div>

        <div class="mt-3 text-xs text-slate-500">
            Cibles exclues du patch management : <?= $disabledCount ?>. Le tri affiche les erreurs, risques OS, updates securite et reboots en premier.
        </div>
    </form>

    <div class="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Collecteurs</h2>
            <span class="text-xs font-semibold text-slate-500">Ordre actuel : apt puis dnf</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2 text-sm">
            <div class="rounded border border-green-200 bg-green-50 p-3">
                <div class="font-mono text-xs font-bold text-green-800">apt</div>
                <div class="mt-1 text-slate-700">Debian, Ubuntu, Proxmox</div>
                <div class="mt-1 text-xs font-semibold text-green-700">Disponible</div>
            </div>
            <div class="rounded border border-green-200 bg-green-50 p-3">
                <div class="font-mono text-xs font-bold text-green-800">dnf</div>
                <div class="mt-1 text-slate-700">Rocky, RHEL-like</div>
                <div class="mt-1 text-xs font-semibold text-green-700">Disponible</div>
            </div>
            <div class="rounded border border-slate-200 bg-slate-50 p-3">
                <div class="font-mono text-xs font-bold text-slate-700">docker</div>
                <div class="mt-1 text-slate-700">Hotes Docker</div>
                <div class="mt-1 text-xs font-semibold text-slate-500">Prevu</div>
            </div>
            <div class="rounded border border-slate-200 bg-slate-50 p-3">
                <div class="font-mono text-xs font-bold text-slate-700">synology_dsm</div>
                <div class="mt-1 text-slate-700">NAS Synology</div>
                <div class="mt-1 text-xs font-semibold text-slate-500">Prevu</div>
            </div>
            <div class="rounded border border-slate-200 bg-slate-50 p-3">
                <div class="font-mono text-xs font-bold text-slate-700">windows_winrm</div>
                <div class="mt-1 text-slate-700">Windows</div>
                <div class="mt-1 text-xs font-semibold text-slate-500">Prevu</div>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-100 text-left">
                <tr>
                    <th class="px-4 py-3 text-sm font-semibold text-slate-700">Cible</th>
                    <th class="px-4 py-3 text-sm font-semibold text-slate-700">Type</th>
                    <th class="px-4 py-3 text-sm font-semibold text-slate-700">Env.</th>
                    <th class="px-4 py-3 text-sm font-semibold text-slate-700">Criticite</th>
                    <th class="px-4 py-3 text-sm font-semibold text-slate-700">Collecteur</th>
                    <th class="px-4 py-3 text-sm font-semibold text-slate-700">Statut patch</th>
                    <th class="px-4 py-3 text-sm font-semibold text-slate-700">Securite</th>
                    <th class="px-4 py-3 text-sm font-semibold text-slate-700">Normales</th>
                    <th class="px-4 py-3 text-sm font-semibold text-slate-700">Reboot</th>
                    <th class="px-4 py-3 text-sm font-semibold text-slate-700">Cycle OS</th>
                    <th class="px-4 py-3 text-sm font-semibold text-slate-700">Priorite</th>
                    <th class="px-4 py-3 text-sm font-semibold text-slate-700">Dernier check</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (!$targets): ?>
                    <tr>
                        <td colspan="12" class="px-4 py-6 text-center text-sm text-slate-500">
                            Aucune cible ne correspond aux filtres.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($targets as $target): ?>
                        <tr class="<?= !empty($target['reboot_required']) ? 'bg-orange-50' : '' ?>">
                            <td class="px-4 py-3">
                                <a href="<?= $baseUrl ?>pages/details-cible.php?id=<?= (int) $target['id'] ?>"
                                   class="font-semibold text-blue-700 hover:underline">
                                    <?= htmlspecialchars($target['name']) ?>
                                </a>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars($target['hostname']) ?></div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <?= htmlspecialchars($targetTypes[$target['target_type'] ?? ''] ?? ($target['target_type'] ?? 'other')) ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <?= htmlspecialchars($environments[$target['environment'] ?? ''] ?? ($target['environment'] ?? 'other')) ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <?= htmlspecialchars($criticalities[$target['criticality'] ?? ''] ?? ($target['criticality'] ?? 'medium')) ?>
                            </td>
                            <td class="px-4 py-3"><?= msmPatchCollectorBadge($target['collector'] ?? null) ?></td>
                            <td class="px-4 py-3"><?= msmPatchStatusBadge($target['patch_status'] ?? null) ?></td>
                            <td class="px-4 py-3 text-sm font-semibold text-red-700">
                                <?= (int) ($target['security_updates_count'] ?? 0) ?>
                            </td>
                            <td class="px-4 py-3 text-sm font-semibold text-slate-800">
                                <?= (int) ($target['normal_updates_count'] ?? 0) ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <?= msmPatchRebootBadge(!empty($target['reboot_required'])) ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <?= msmPatchOsLifecycleBadge($target['os_support_status'] ?? null) ?>
                                <?php if (!empty($target['os_upgrade_available'])): ?>
                                    <div class="mt-1 text-xs font-semibold text-blue-700">
                                        Upgrade vers <?= htmlspecialchars($target['os_upgrade_target_label'] ?: $target['os_upgrade_target_version']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($target['os_support_ends_at'])): ?>
                                    <div class="mt-1 text-xs text-slate-500">
                                        Fin : <?= htmlspecialchars($target['os_support_ends_at']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <?= msmPatchActionBadges($target) ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <?= msmPatchFormatDate($target['checked_at'] ?? null) ?>
                                <?php if (!empty($target['os_lifecycle_checked_at'])): ?>
                                    <div class="mt-1 text-xs text-slate-500">
                                        OS : <?= htmlspecialchars($target['os_lifecycle_checked_at']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($target['error_message'])): ?>
                                    <div class="mt-1 text-xs text-red-600"><?= htmlspecialchars($target['error_message']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
