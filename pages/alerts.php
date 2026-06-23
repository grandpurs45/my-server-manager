<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/header.php';

use MSM\AlertRepository;

$repository = new AlertRepository($pdo);

$allowedStatuses = ['active', 'resolved', 'all'];
$allowedOperatorStates = ['visible', 'acknowledged', 'ignored', 'all'];
$allowedSeverities = ['', 'critical', 'warning', 'info'];
$allowedSources = ['', 'supervision', 'patch_management', 'os_lifecycle', 'security'];

$status = $_GET['status'] ?? 'active';
$operatorState = $_GET['operator_state'] ?? 'visible';
$severity = $_GET['severity'] ?? '';
$source = $_GET['source'] ?? '';

if (!in_array($status, $allowedStatuses, true)) {
    $status = 'active';
}
if (!in_array($operatorState, $allowedOperatorStates, true)) {
    $operatorState = 'visible';
}
if (!in_array($severity, $allowedSeverities, true)) {
    $severity = '';
}
if (!in_array($source, $allowedSources, true)) {
    $source = '';
}

$alerts = $repository->getAlerts([
    'status' => $status,
    'operator_state' => $operatorState,
    'severity' => $severity,
    'source' => $source,
]);
$eventsByAlert = $repository->getEventsForAlerts(array_map(
    fn (array $alert): int => (int) ($alert['id'] ?? 0),
    $alerts
));
$counts = $repository->getActiveAlertCounts();
$redirectQuery = http_build_query([
    'status' => $status,
    'operator_state' => $operatorState,
    'severity' => $severity,
    'source' => $source,
]);
$redirect = 'alerts.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : '');

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

function msmAlertOperatorBadges(array $alert): array
{
    $badges = [];

    if (($alert['status'] ?? '') !== 'active') {
        return $badges;
    }

    if (!empty($alert['ignored_at'])) {
        $badges[] = ['Ignoree', 'bg-slate-800 text-white'];
    } elseif (!empty($alert['acknowledged_at'])) {
        $badges[] = ['Acquittee', 'bg-blue-100 text-blue-700'];
    } else {
        $badges[] = ['A traiter', 'bg-orange-100 text-orange-700'];
    }

    return $badges;
}

function msmAlertEventLabel(string $eventType): string
{
    return match ($eventType) {
        'opened' => 'Ouverture',
        'updated' => 'Mise a jour',
        'resolved' => 'Resolution',
        'acknowledged' => 'Acquittement',
        'unacknowledged' => 'Acquittement retire',
        'ignored' => 'Ignoree',
        'reactivated' => 'Reactivee',
        default => $eventType,
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

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
            <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-6">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">A traiter</div>
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
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Acquittees</div>
            <div class="mt-2 text-3xl font-bold text-blue-700"><?= (int) ($counts['acknowledged'] ?? 0) ?></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Ignorees</div>
            <div class="mt-2 text-3xl font-bold text-slate-700"><?= (int) ($counts['ignored'] ?? 0) ?></div>
        </div>
    </div>

    <form method="get" class="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
            <label class="text-sm font-semibold text-slate-700">
                Statut
                <select name="status" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    <?php foreach (['active' => 'Actives', 'resolved' => 'Resolues', 'all' => 'Toutes'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">
                Traitement
                <select name="operator_state" class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
                    <?php foreach (['visible' => 'A traiter', 'acknowledged' => 'Acquittees', 'ignored' => 'Ignorees', 'all' => 'Tout'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $operatorState === $value ? 'selected' : '' ?>><?= $label ?></option>
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
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (!$alerts): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                            Aucune alerte pour ces filtres.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($alerts as $alert): ?>
                    <?php $alertEvents = $eventsByAlert[(int) ($alert['id'] ?? 0)] ?? []; ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 align-top">
                            <div class="flex flex-col gap-2">
                                <div class="font-semibold text-slate-900"><?= htmlspecialchars($alert['title']) ?></div>
                                <div class="text-sm text-slate-600"><?= htmlspecialchars($alert['message']) ?></div>
                                <span class="w-fit rounded px-2 py-1 text-xs font-semibold <?= msmAlertBadgeClass($alert['severity'] ?? 'info') ?>">
                                    <?= htmlspecialchars($alert['severity'] ?? 'info') ?>
                                </span>
                                <?php if ($alertEvents): ?>
                                    <details class="mt-1 rounded border border-gray-200 bg-slate-50 p-2 text-xs">
                                        <summary class="cursor-pointer font-semibold text-slate-700">
                                            Historique recent
                                        </summary>
                                        <div class="mt-2 space-y-2">
                                            <?php foreach ($alertEvents as $event): ?>
                                                <div class="border-l-2 border-slate-300 pl-2">
                                                    <div class="font-semibold text-slate-800">
                                                        <?= htmlspecialchars(msmAlertEventLabel((string) ($event['event_type'] ?? ''))) ?>
                                                        <span class="font-normal text-slate-500">
                                                            - <?= htmlspecialchars($event['created_at'] ?? '') ?>
                                                        </span>
                                                    </div>
                                                    <div class="text-slate-600">
                                                        <?= htmlspecialchars($event['message'] ?? '') ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endif; ?>
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
                            <div class="flex flex-col gap-2">
                                <span class="w-fit rounded px-2 py-1 text-xs font-semibold <?= msmAlertStatusClass($alert['status'] ?? 'unknown') ?>">
                                    <?= htmlspecialchars($alert['status'] ?? 'unknown') ?>
                                </span>
                                <?php foreach (msmAlertOperatorBadges($alert) as [$label, $class]): ?>
                                    <span class="w-fit rounded px-2 py-1 text-xs font-semibold <?= $class ?>">
                                        <?= htmlspecialchars($label) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 align-top text-xs text-slate-600">
                            <div>Premiere : <?= htmlspecialchars($alert['first_seen_at'] ?? '') ?></div>
                            <div>Derniere : <?= htmlspecialchars($alert['last_seen_at'] ?? '') ?></div>
                            <?php if (!empty($alert['resolved_at'])): ?>
                                <div>Resolution : <?= htmlspecialchars($alert['resolved_at']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($alert['acknowledged_at'])): ?>
                                <div>Acquittee : <?= htmlspecialchars($alert['acknowledged_at']) ?></div>
                                <?php if (!empty($alert['acknowledged_comment'])): ?>
                                    <div class="mt-1 rounded bg-blue-50 px-2 py-1 text-blue-800">
                                        <?= htmlspecialchars($alert['acknowledged_comment']) ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($alert['ignored_at'])): ?>
                                <div>Ignoree : <?= htmlspecialchars($alert['ignored_at']) ?></div>
                                <?php if (!empty($alert['ignored_comment'])): ?>
                                    <div class="mt-1 rounded bg-slate-100 px-2 py-1 text-slate-700">
                                        <?= htmlspecialchars($alert['ignored_comment']) ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 align-top text-sm font-semibold text-slate-700">
                            <?= (int) ($alert['occurrence_count'] ?? 0) ?>
                        </td>
                        <td class="px-4 py-3 align-top text-sm">
                            <?php if (($alert['status'] ?? '') === 'active'): ?>
                                <details class="w-64 rounded border border-gray-200 bg-white p-2">
                                    <summary class="cursor-pointer text-sm font-semibold text-blue-700">Traiter</summary>
                                    <form method="post" action="<?= $baseUrl ?>pages/alert-action.php" class="mt-3 space-y-2">
                                        <?= msmCsrfField() ?>
                                        <input type="hidden" name="alert_id" value="<?= (int) $alert['id'] ?>">
                                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                                        <textarea name="comment"
                                                  rows="2"
                                                  class="w-full rounded border border-gray-300 px-2 py-1 text-xs"
                                                  placeholder="Commentaire optionnel"></textarea>
                                        <div class="flex flex-wrap gap-2">
                                            <?php if (!empty($alert['ignored_at'])): ?>
                                                <button type="submit" name="action" value="unignore" class="rounded bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                                    Reactiver
                                                </button>
                                            <?php elseif (!empty($alert['acknowledged_at'])): ?>
                                                <button type="submit" name="action" value="unacknowledge" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-gray-50">
                                                    Retirer acquittement
                                                </button>
                                                <button type="submit" name="action" value="ignore" class="rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">
                                                    Ignorer
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="acknowledge" class="rounded bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                                    Acquitter
                                                </button>
                                                <button type="submit" name="action" value="ignore" class="rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">
                                                    Ignorer
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </details>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
