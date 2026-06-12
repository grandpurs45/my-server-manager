<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/bootstrap.php';

use MSM\PatchStatusRepository;
use MSM\AlertRepository;
use MSM\SecurityStatusRepository;
use MSM\SettingsManager;

$settingsManager = new SettingsManager($pdo);
$patchRepository = new PatchStatusRepository($pdo);
$patchTargets = $patchRepository->getOverview();
$securityRepository = new SecurityStatusRepository($pdo);
$securityTargets = $securityRepository->getOverview();
$alertRepository = new AlertRepository($pdo);
$alertCounts = $alertRepository->getActiveAlertCounts();

$serverStats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'down' THEN 1 ELSE 0 END) AS down_count,
        SUM(CASE WHEN ssh_enabled = 1 AND ssh_status <> 'success' THEN 1 ELSE 0 END) AS ssh_error_count,
        MAX(last_check) AS last_supervision_check
    FROM servers
")->fetch(PDO::FETCH_ASSOC) ?: [];

$latestPatchCheck = $pdo->query("SELECT MAX(checked_at) FROM patch_checks")->fetchColumn() ?: null;
$latestOsLifecycleCheck = $pdo->query("SELECT MAX(checked_at) FROM os_lifecycle_checks")->fetchColumn() ?: null;
$latestSecurityCheck = $pdo->query("SELECT MAX(checked_at) FROM security_checks")->fetchColumn() ?: null;

function msmDashboardCheckRuntime(SettingsManager $settingsManager, string $category): array
{
    return [
        'attempt' => $settingsManager->get($category, 'check_last_attempt_at'),
        'run' => $settingsManager->get($category, 'check_last_run_at'),
        'finished' => $settingsManager->get($category, 'check_last_finished_at'),
        'status' => $settingsManager->get($category, 'check_last_status'),
        'message' => $settingsManager->get($category, 'check_last_message'),
    ];
}

$summary = [
    'servers_down' => (int) ($serverStats['down_count'] ?? 0),
    'ssh_errors' => (int) ($serverStats['ssh_error_count'] ?? 0),
    'security_updates' => array_sum(array_map(fn (array $target): int => (int) ($target['security_updates_count'] ?? 0), $patchTargets)),
    'reboot_required' => array_sum(array_map(fn (array $target): int => (int) ($target['reboot_required'] ?? 0), $patchTargets)),
    'os_upgrades' => array_sum(array_map(fn (array $target): int => (int) ($target['os_upgrade_available'] ?? 0), $patchTargets)),
    'os_risks' => count(array_filter($patchTargets, fn (array $target): bool => in_array($target['os_support_status'] ?? null, ['eol', 'eol_soon'], true))),
    'security_risks' => count(array_filter($securityTargets, fn (array $target): bool => in_array($target['security_status'] ?? null, ['warning', 'error'], true))),
    'security_exposed_ports' => array_sum(array_map(fn (array $target): int => (int) ($target['exposed_ports_count'] ?? 0), $securityTargets)),
    'security_firewall_warnings' => count(array_filter($securityTargets, fn (array $target): bool => in_array($target['firewall_status'] ?? null, ['inactif', 'not_installed', null], true))),
    'active_alerts' => (int) ($alertCounts['total'] ?? 0),
    'critical_alerts' => (int) ($alertCounts['critical'] ?? 0),
];

$priorities = [];

$serverStmt = $pdo->query("
    SELECT id, name, hostname, target_type, status, ssh_enabled, ssh_status, last_check
    FROM servers
    WHERE status = 'down'
       OR (ssh_enabled = 1 AND ssh_status <> 'success')
    ORDER BY name ASC
");

foreach ($serverStmt->fetchAll(PDO::FETCH_ASSOC) as $server) {
    if (($server['status'] ?? '') === 'down') {
        $priorities[] = [
            'score' => 0,
            'label' => 'Serveur down',
            'name' => $server['name'],
            'hostname' => $server['hostname'],
            'target_type' => $server['target_type'] ?? 'other',
            'href' => $baseUrl . 'pages/details-cible.php?id=' . (int) $server['id'],
            'badge' => 'bg-red-100 text-red-700',
        ];
    }

    if (!empty($server['ssh_enabled']) && ($server['ssh_status'] ?? '') !== 'success') {
        $priorities[] = [
            'score' => 1,
            'label' => 'SSH en erreur',
            'name' => $server['name'],
            'hostname' => $server['hostname'],
            'target_type' => $server['target_type'] ?? 'other',
            'href' => $baseUrl . 'pages/details-cible.php?id=' . (int) $server['id'],
            'badge' => 'bg-red-100 text-red-700',
        ];
    }
}

foreach ($patchTargets as $target) {
    if (($target['patch_status'] ?? null) === 'error') {
        $priorities[] = [
            'score' => 2,
            'label' => 'Erreur patch',
            'name' => $target['name'],
            'hostname' => $target['hostname'],
            'target_type' => $target['target_type'] ?? 'other',
            'href' => $baseUrl . 'pages/patch-management.php?action=errors',
            'badge' => 'bg-red-100 text-red-700',
        ];
    }

    if (in_array($target['os_support_status'] ?? null, ['eol', 'eol_soon'], true)) {
        $priorities[] = [
            'score' => 3,
            'label' => ($target['os_support_status'] ?? '') === 'eol' ? 'OS obsolete' : 'Fin support OS',
            'name' => $target['name'],
            'hostname' => $target['hostname'],
            'target_type' => $target['target_type'] ?? 'other',
            'href' => $baseUrl . 'pages/patch-management.php?action=os_risk',
            'badge' => 'bg-red-100 text-red-700',
        ];
    }

    if ((int) ($target['security_updates_count'] ?? 0) > 0) {
        $priorities[] = [
            'score' => 4,
            'label' => (int) $target['security_updates_count'] . ' update(s) securite',
            'name' => $target['name'],
            'hostname' => $target['hostname'],
            'target_type' => $target['target_type'] ?? 'other',
            'href' => $baseUrl . 'pages/patch-management.php?action=security',
            'badge' => 'bg-red-100 text-red-700',
        ];
    }

    if (!empty($target['reboot_required'])) {
        $priorities[] = [
            'score' => 5,
            'label' => 'Reboot requis',
            'name' => $target['name'],
            'hostname' => $target['hostname'],
            'target_type' => $target['target_type'] ?? 'other',
            'href' => $baseUrl . 'pages/patch-management.php?action=reboot',
            'badge' => 'bg-orange-100 text-orange-800',
        ];
    }

    if (!empty($target['os_upgrade_available'])) {
        $priorities[] = [
            'score' => 6,
            'label' => 'Upgrade OS disponible',
            'name' => $target['name'],
            'hostname' => $target['hostname'],
            'target_type' => $target['target_type'] ?? 'other',
            'href' => $baseUrl . 'pages/patch-management.php?action=os_upgrade',
            'badge' => 'bg-blue-100 text-blue-700',
        ];
    }
}

foreach ($securityTargets as $target) {
    $securityStatus = $target['security_status'] ?? null;
    $exposedPorts = (int) ($target['exposed_ports_count'] ?? 0);
    $firewallStatus = $target['firewall_status'] ?? null;

    if ($securityStatus === 'error') {
        $priorities[] = [
            'score' => 2,
            'label' => 'Erreur securite',
            'name' => $target['name'],
            'hostname' => $target['hostname'],
            'target_type' => $target['target_type'] ?? 'other',
            'href' => $baseUrl . 'pages/details-securite.php?id=' . (int) $target['id'],
            'badge' => 'bg-red-100 text-red-700',
        ];
        continue;
    }

    if ($exposedPorts > 0) {
        $priorities[] = [
            'score' => 3,
            'label' => $exposedPorts . ' port(s) expose(s)',
            'name' => $target['name'],
            'hostname' => $target['hostname'],
            'target_type' => $target['target_type'] ?? 'other',
            'href' => $baseUrl . 'pages/details-securite.php?id=' . (int) $target['id'],
            'badge' => 'bg-red-100 text-red-700',
        ];
    } elseif (in_array($firewallStatus, ['inactif', 'not_installed', null], true)) {
        $priorities[] = [
            'score' => 4,
            'label' => 'Firewall a verifier',
            'name' => $target['name'],
            'hostname' => $target['hostname'],
            'target_type' => $target['target_type'] ?? 'other',
            'href' => $baseUrl . 'pages/details-securite.php?id=' . (int) $target['id'],
            'badge' => 'bg-yellow-100 text-yellow-800',
        ];
    }
}

usort($priorities, fn (array $a, array $b): int => $a['score'] <=> $b['score'] ?: strcasecmp($a['name'], $b['name']));
$priorities = array_slice($priorities, 0, 8);

function msmDashboardDate(?string $date): string
{
    return $date ? htmlspecialchars($date) : 'Jamais';
}

function msmDashboardFreshness(?string $date, int $maxAgeSeconds, ?string $status = null): array
{
    if ($status === 'error') {
        return ['label' => 'Erreur', 'class' => 'text-red-700 bg-red-50 border-red-200'];
    }

    if ($status === 'running') {
        return ['label' => 'En cours', 'class' => 'text-blue-700 bg-blue-50 border-blue-200'];
    }

    if (!$date) {
        return ['label' => 'Jamais', 'class' => 'text-red-700 bg-red-50 border-red-200'];
    }

    try {
        $checkedAt = new DateTimeImmutable($date);
    } catch (Exception) {
        return ['label' => 'Date invalide', 'class' => 'text-red-700 bg-red-50 border-red-200'];
    }

    $ageSeconds = time() - $checkedAt->getTimestamp();
    if ($ageSeconds <= $maxAgeSeconds) {
        return ['label' => 'A jour', 'class' => 'text-green-700 bg-green-50 border-green-200'];
    }

    return ['label' => 'Ancien', 'class' => 'text-yellow-800 bg-yellow-50 border-yellow-200'];
}

function msmDashboardStatusLabel(?string $status): string
{
    return match ($status) {
        'success' => 'termine',
        'skipped' => 'saute',
        'running' => 'en cours',
        'error' => 'en erreur',
        default => $status ?: '-',
    };
}

$supervisionInterval = (int) ($settingsManager->get('supervision', 'check_interval_minutes') ?? 10);
if ($supervisionInterval < 1) {
    $supervisionInterval = 10;
}

$patchInterval = (int) ($settingsManager->get('patch_management', 'check_interval_hours') ?? 6);
if ($patchInterval < 1) {
    $patchInterval = 6;
}

$osLifecycleInterval = (int) ($settingsManager->get('os_lifecycle', 'check_interval_hours') ?? 168);
if ($osLifecycleInterval < 1) {
    $osLifecycleInterval = 168;
}

$securityInterval = (int) ($settingsManager->get('security', 'check_interval_hours') ?? 24);
if ($securityInterval < 1) {
    $securityInterval = 24;
}

$alertingInterval = (int) ($settingsManager->get('alerting', 'check_interval_minutes') ?? 5);
if ($alertingInterval < 1) {
    $alertingInterval = 5;
}

$latestAlertingCheck = $settingsManager->get('alerting', 'check_last_run_at');

$supervisionRuntime = msmDashboardCheckRuntime($settingsManager, 'supervision');
$patchRuntime = msmDashboardCheckRuntime($settingsManager, 'patch_management');
$osLifecycleRuntime = msmDashboardCheckRuntime($settingsManager, 'os_lifecycle');
$securityRuntime = msmDashboardCheckRuntime($settingsManager, 'security');
$alertingRuntime = msmDashboardCheckRuntime($settingsManager, 'alerting');

$freshness = [
    [
        'name' => 'Supervision',
        'execution_date' => $supervisionRuntime['attempt'] ?: ($supervisionRuntime['run'] ?: ($serverStats['last_supervision_check'] ?? null)),
        'result_date' => $serverStats['last_supervision_check'] ?? null,
        'interval' => $supervisionInterval . ' min',
        'state' => msmDashboardFreshness($supervisionRuntime['attempt'] ?: ($supervisionRuntime['run'] ?: ($serverStats['last_supervision_check'] ?? null)), max(900, $supervisionInterval * 60 * 3), $supervisionRuntime['status']),
        'status' => $supervisionRuntime['status'],
        'message' => $supervisionRuntime['message'],
        'href' => $baseUrl . 'pages/supervision.php',
    ],
    [
        'name' => 'Patch Management',
        'execution_date' => $patchRuntime['attempt'] ?: ($patchRuntime['run'] ?: $latestPatchCheck),
        'result_date' => $latestPatchCheck,
        'interval' => $patchInterval . ' h',
        'state' => msmDashboardFreshness($patchRuntime['attempt'] ?: ($patchRuntime['run'] ?: $latestPatchCheck), max(86400, $patchInterval * 3600 * 2), $patchRuntime['status']),
        'status' => $patchRuntime['status'],
        'message' => $patchRuntime['message'],
        'href' => $baseUrl . 'pages/patch-management.php',
    ],
    [
        'name' => 'Cycle de vie OS',
        'execution_date' => $osLifecycleRuntime['attempt'] ?: ($osLifecycleRuntime['run'] ?: $latestOsLifecycleCheck),
        'result_date' => $latestOsLifecycleCheck,
        'interval' => $osLifecycleInterval . ' h',
        'state' => msmDashboardFreshness($osLifecycleRuntime['attempt'] ?: ($osLifecycleRuntime['run'] ?: $latestOsLifecycleCheck), max(604800, $osLifecycleInterval * 3600 * 2), $osLifecycleRuntime['status']),
        'status' => $osLifecycleRuntime['status'],
        'message' => $osLifecycleRuntime['message'],
        'href' => $baseUrl . 'pages/patch-management.php?action=os_upgrade',
    ],
    [
        'name' => 'Securite',
        'execution_date' => $securityRuntime['attempt'] ?: ($securityRuntime['run'] ?: $latestSecurityCheck),
        'result_date' => $latestSecurityCheck,
        'interval' => $securityInterval . ' h',
        'state' => msmDashboardFreshness($securityRuntime['attempt'] ?: ($securityRuntime['run'] ?: $latestSecurityCheck), max(86400, $securityInterval * 3600 * 2), $securityRuntime['status']),
        'status' => $securityRuntime['status'],
        'message' => $securityRuntime['message'],
        'href' => $baseUrl . 'pages/securite-serveurs.php',
    ],
    [
        'name' => 'Alerting',
        'execution_date' => $alertingRuntime['attempt'] ?: ($alertingRuntime['run'] ?: $latestAlertingCheck),
        'result_date' => $latestAlertingCheck,
        'interval' => $alertingInterval . ' min',
        'state' => msmDashboardFreshness($alertingRuntime['attempt'] ?: ($alertingRuntime['run'] ?: $latestAlertingCheck), max(900, $alertingInterval * 60 * 3), $alertingRuntime['status']),
        'status' => $alertingRuntime['status'],
        'message' => $alertingRuntime['message'],
        'href' => $baseUrl . 'pages/alerts.php',
    ],
];
?>

<div class="p-6">
    <div class="mb-6 flex flex-col gap-2">
        <h1 class="text-2xl font-bold text-slate-900">Dashboard exploitation</h1>
        <p class="text-sm text-slate-600">Vue de synthese des derniers resultats connus. Aucun check lourd n'est lance depuis cette page.</p>
    </div>

    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
        <a href="<?= $baseUrl ?>pages/supervision.php" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-300">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase text-slate-500">Serveurs down</div>
                <i data-lucide="server-off" class="h-5 w-5 text-red-500"></i>
            </div>
            <div class="mt-2 text-3xl font-bold text-red-700"><?= $summary['servers_down'] ?></div>
        </a>

        <a href="<?= $baseUrl ?>pages/alerts.php" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-300">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase text-slate-500">Alertes actives</div>
                <i data-lucide="bell-ring" class="h-5 w-5 text-red-500"></i>
            </div>
            <div class="mt-2 text-3xl font-bold text-red-700"><?= $summary['active_alerts'] ?></div>
            <div class="mt-1 text-xs text-slate-500">
                <?= $summary['critical_alerts'] ?> critique(s)
            </div>
        </a>

        <a href="<?= $baseUrl ?>pages/supervision.php" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-300">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase text-slate-500">SSH en erreur</div>
                <i data-lucide="terminal-square" class="h-5 w-5 text-red-500"></i>
            </div>
            <div class="mt-2 text-3xl font-bold text-red-700"><?= $summary['ssh_errors'] ?></div>
        </a>

        <a href="<?= $baseUrl ?>pages/patch-management.php?action=security" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-300">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase text-slate-500">Updates securite</div>
                <i data-lucide="shield-alert" class="h-5 w-5 text-red-500"></i>
            </div>
            <div class="mt-2 text-3xl font-bold text-red-700"><?= $summary['security_updates'] ?></div>
        </a>

        <a href="<?= $baseUrl ?>pages/securite-serveurs.php" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-300">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase text-slate-500">Risques securite</div>
                <i data-lucide="shield-warning" class="h-5 w-5 text-red-500"></i>
            </div>
            <div class="mt-2 text-3xl font-bold text-red-700"><?= $summary['security_risks'] ?></div>
            <div class="mt-1 text-xs text-slate-500">
                <?= $summary['security_exposed_ports'] ?> port(s) expose(s), <?= $summary['security_firewall_warnings'] ?> firewall a verifier
            </div>
        </a>

        <a href="<?= $baseUrl ?>pages/patch-management.php?action=reboot" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-300">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase text-slate-500">Reboots requis</div>
                <i data-lucide="rotate-cw" class="h-5 w-5 text-orange-500"></i>
            </div>
            <div class="mt-2 text-3xl font-bold text-orange-700"><?= $summary['reboot_required'] ?></div>
        </a>

        <a href="<?= $baseUrl ?>pages/patch-management.php?action=os_upgrade" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-300">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase text-slate-500">Upgrades OS</div>
                <i data-lucide="arrow-up-circle" class="h-5 w-5 text-blue-500"></i>
            </div>
            <div class="mt-2 text-3xl font-bold text-blue-700"><?= $summary['os_upgrades'] ?></div>
        </a>

        <a href="<?= $baseUrl ?>pages/patch-management.php?action=os_risk" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-300">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase text-slate-500">OS a risque</div>
                <i data-lucide="calendar-warning" class="h-5 w-5 text-yellow-600"></i>
            </div>
            <div class="mt-2 text-3xl font-bold text-yellow-700"><?= $summary['os_risks'] ?></div>
        </a>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <section class="xl:col-span-2 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">Priorites</h2>
                <a href="<?= $baseUrl ?>pages/patch-management.php?action=needs_action" class="text-sm font-semibold text-blue-700 hover:underline">
                    Voir Patch Management
                </a>
            </div>

            <?php if (!$priorities): ?>
                <p class="rounded border border-green-200 bg-green-50 px-3 py-3 text-sm font-semibold text-green-700">
                    Aucune priorite operationnelle detectee.
                </p>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($priorities as $priority): ?>
                        <a href="<?= htmlspecialchars($priority['href']) ?>" class="flex items-center justify-between gap-4 py-3 hover:bg-slate-50">
                            <div>
                                <div class="font-semibold text-slate-900"><?= htmlspecialchars($priority['name']) ?></div>
                                <div class="text-xs text-slate-500">
                                    <?= htmlspecialchars($priority['hostname']) ?> · <?= htmlspecialchars($priority['target_type']) ?>
                                </div>
                            </div>
                            <span class="shrink-0 rounded px-2 py-1 text-xs font-semibold <?= htmlspecialchars($priority['badge']) ?>">
                                <?= htmlspecialchars($priority['label']) ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Fraicheur des checks</h2>
            <div class="space-y-3">
                <?php foreach ($freshness as $item): ?>
                    <a href="<?= htmlspecialchars($item['href']) ?>" class="block rounded border border-gray-200 p-3 hover:border-blue-300">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-semibold text-slate-900"><?= htmlspecialchars($item['name']) ?></div>
                            <span class="rounded border px-2 py-1 text-xs font-semibold <?= htmlspecialchars($item['state']['class']) ?>">
                                <?= htmlspecialchars($item['state']['label']) ?>
                            </span>
                        </div>
                        <div class="mt-2 text-xs text-slate-500">
                            Derniere execution : <?= msmDashboardDate($item['execution_date']) ?>
                        </div>
                        <?php if (!empty($item['result_date'])): ?>
                            <div class="text-xs text-slate-500">
                                Dernier resultat : <?= msmDashboardDate($item['result_date']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="text-xs text-slate-500">
                            Intervalle : <?= htmlspecialchars($item['interval']) ?>
                        </div>
                        <?php if (!empty($item['status']) || !empty($item['message'])): ?>
                            <div class="mt-2 rounded bg-slate-50 px-2 py-1 text-xs text-slate-600">
                                <?php if (!empty($item['status'])): ?>
                                    <span class="font-semibold">Script <?= htmlspecialchars(msmDashboardStatusLabel($item['status'])) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['message'])): ?>
                                    <span><?= !empty($item['status']) ? ' - ' : '' ?><?= htmlspecialchars($item['message']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2">
                <a href="<?= $baseUrl ?>pages/diagnostic.php" class="inline-flex items-center justify-center gap-2 rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-gray-50">
                    <i data-lucide="stethoscope" class="h-4 w-4"></i>
                    Diagnostic
                </a>
                <a href="<?= $baseUrl ?>metrics.php" class="inline-flex items-center justify-center gap-2 rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-gray-50">
                    <i data-lucide="activity" class="h-4 w-4"></i>
                    Metrics
                </a>
            </div>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
