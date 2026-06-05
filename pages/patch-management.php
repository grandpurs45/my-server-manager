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

require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Patch Management</h1>
        <p class="mt-1 text-sm text-slate-600">
            Derniers resultats connus des checks de mises a jour. Aucun check lourd n'est lance depuis cette page.
        </p>
    </div>

    <div class="mb-4 grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Cibles activees</div>
            <div class="mt-1 text-2xl font-bold text-slate-900"><?= count($targets) ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Cibles exclues</div>
            <div class="mt-1 text-2xl font-bold text-slate-900"><?= $disabledCount ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Updates securite</div>
            <div class="mt-1 text-2xl font-bold text-red-700">
                <?= array_sum(array_map(fn ($target) => (int) ($target['security_updates_count'] ?? 0), $targets)) ?>
            </div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Reboots requis</div>
            <div class="mt-1 text-2xl font-bold text-yellow-700">
                <?= array_sum(array_map(fn ($target) => (int) ($target['reboot_required'] ?? 0), $targets)) ?>
            </div>
        </div>
    </div>

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
                    <th class="px-4 py-3 text-sm font-semibold text-slate-700">Dernier check</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (!$targets): ?>
                    <tr>
                        <td colspan="10" class="px-4 py-6 text-center text-sm text-slate-500">
                            Aucune cible n'est activee pour le patch management.
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
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <?= msmPatchFormatDate($target['checked_at'] ?? null) ?>
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
