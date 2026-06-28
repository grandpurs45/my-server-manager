<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/classes/SetupAssistant.php';

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
$enabledAlertRules = $alertRepository->getEnabledRules();
$securityExposedPortsRuleEnabled = isset($enabledAlertRules['security_exposed_ports']);
$securityFirewallRuleEnabled = isset($enabledAlertRules['security_firewall_disabled']);
$temperatureWarningRule = $enabledAlertRules['hardware_temperature_warning'] ?? null;
$temperatureCriticalRule = $enabledAlertRules['hardware_temperature_critical'] ?? null;
$temperatureWarningThreshold = max(1, (int) ($temperatureWarningRule['threshold_value'] ?? 70));
$temperatureCriticalThreshold = max(1, (int) ($temperatureCriticalRule['threshold_value'] ?? 85));
$smartFailedRuleEnabled = isset($enabledAlertRules['hardware_smart_failed']);
$smartMediaRule = $enabledAlertRules['hardware_smart_media_errors'] ?? null;
$smartWearWarningRule = $enabledAlertRules['hardware_smart_wear_warning'] ?? null;
$smartWearCriticalRule = $enabledAlertRules['hardware_smart_wear_critical'] ?? null;
$smartMediaThreshold = max(1, (int) ($smartMediaRule['threshold_value'] ?? 1));
$smartWearWarningThreshold = max(1, (int) ($smartWearWarningRule['threshold_value'] ?? 80));
$smartWearCriticalThreshold = max(1, (int) ($smartWearCriticalRule['threshold_value'] ?? 95));

$serverStats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'down' THEN 1 ELSE 0 END) AS down_count,
        SUM(CASE WHEN ssh_enabled = 1 AND ssh_status <> 'success' THEN 1 ELSE 0 END) AS ssh_error_count,
        MAX(last_check) AS last_supervision_check
    FROM servers
")->fetch(PDO::FETCH_ASSOC) ?: [];

$hardwareTargets = $pdo->query("
    SELECT
        s.id,
        s.name,
        s.hostname,
        s.target_type,
        s.hardware_profile,
        hc.status AS hardware_status,
        hc.max_temperature_celsius,
        hc.checked_at
    FROM servers s
    LEFT JOIN hardware_health_checks hc
        ON hc.id = (
            SELECT hc2.id
            FROM hardware_health_checks hc2
            WHERE hc2.server_id = s.id
            ORDER BY hc2.checked_at DESC, hc2.id DESC
            LIMIT 1
        )
    WHERE s.hardware_profile IN ('physical', 'appliance')
    ORDER BY s.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$hardwareTemperatures = array_values(array_filter(
    $hardwareTargets,
    fn (array $target): bool => ($target['hardware_status'] ?? '') === 'ok'
        && $target['max_temperature_celsius'] !== null
));
$maxHardwareTemperature = $hardwareTemperatures === []
    ? null
    : max(array_map(fn (array $target): float => (float) $target['max_temperature_celsius'], $hardwareTemperatures));
$criticalHardwareTargets = $temperatureCriticalRule === null
    ? []
    : array_values(array_filter(
        $hardwareTemperatures,
        fn (array $target): bool => (float) $target['max_temperature_celsius'] >= $temperatureCriticalThreshold
    ));
$warningHardwareTargets = $temperatureWarningRule === null
    ? []
    : array_values(array_filter(
        $hardwareTemperatures,
        fn (array $target): bool => (float) $target['max_temperature_celsius'] >= $temperatureWarningThreshold
            && ($temperatureCriticalRule === null || (float) $target['max_temperature_celsius'] < $temperatureCriticalThreshold)
    ));

$smartDisks = $pdo->query("
    SELECT
        s.id AS server_id,
        s.name AS server_name,
        s.hostname,
        s.target_type,
        d.device_name,
        d.model_name,
        d.smart_passed,
        d.percentage_used,
        d.media_errors
    FROM servers s
    INNER JOIN hardware_health_checks hc
        ON hc.id = (
            SELECT hc2.id
            FROM hardware_health_checks hc2
            WHERE hc2.server_id = s.id
            ORDER BY hc2.checked_at DESC, hc2.id DESC
            LIMIT 1
        )
    INNER JOIN hardware_smart_disks d
        ON d.hardware_check_id = hc.id
    WHERE s.hardware_profile = 'physical'
    ORDER BY s.name ASC, d.device_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$smartFailedDisks = $smartFailedRuleEnabled
    ? array_values(array_filter(
        $smartDisks,
        fn (array $disk): bool => $disk['smart_passed'] !== null && (int) $disk['smart_passed'] === 0
    ))
    : [];
$smartMediaErrorDisks = $smartMediaRule === null
    ? []
    : array_values(array_filter(
        $smartDisks,
        fn (array $disk): bool => $disk['media_errors'] !== null
            && (int) $disk['media_errors'] >= $smartMediaThreshold
    ));
$smartWearCriticalDisks = $smartWearCriticalRule === null
    ? []
    : array_values(array_filter(
        $smartDisks,
        fn (array $disk): bool => $disk['percentage_used'] !== null
            && (float) $disk['percentage_used'] >= $smartWearCriticalThreshold
    ));
$smartWearWarningDisks = $smartWearWarningRule === null
    ? []
    : array_values(array_filter(
        $smartDisks,
        fn (array $disk): bool => $disk['percentage_used'] !== null
            && (float) $disk['percentage_used'] >= $smartWearWarningThreshold
            && ($smartWearCriticalRule === null || (float) $disk['percentage_used'] < $smartWearCriticalThreshold)
    ));
$smartMaxWear = array_reduce(
    $smartDisks,
    function (?float $maximum, array $disk): ?float {
        if ($disk['percentage_used'] === null) {
            return $maximum;
        }

        $wear = (float) $disk['percentage_used'];
        return $maximum === null ? $wear : max($maximum, $wear);
    },
    null
);

$summary = [
    'servers_down' => (int) ($serverStats['down_count'] ?? 0),
    'ssh_errors' => (int) ($serverStats['ssh_error_count'] ?? 0),
    'security_updates' => array_sum(array_map(fn (array $target): int => (int) ($target['security_updates_count'] ?? 0), $patchTargets)),
    'reboot_required' => array_sum(array_map(fn (array $target): int => (int) ($target['reboot_required'] ?? 0), $patchTargets)),
    'os_upgrades' => array_sum(array_map(fn (array $target): int => (int) ($target['os_upgrade_available'] ?? 0), $patchTargets)),
    'os_risks' => count(array_filter($patchTargets, fn (array $target): bool => in_array($target['os_support_status'] ?? null, ['eol', 'eol_soon'], true))),
    'security_risks' => count(array_filter($securityTargets, fn (array $target): bool => ($target['security_status'] ?? null) === 'error'
        || ($securityExposedPortsRuleEnabled && (int) ($target['exposed_ports_count'] ?? 0) > 0)
        || ($securityFirewallRuleEnabled && in_array($target['firewall_status'] ?? null, ['inactif', 'not_installed', null], true)))),
    'security_exposed_ports' => $securityExposedPortsRuleEnabled
        ? array_sum(array_map(fn (array $target): int => (int) ($target['exposed_ports_count'] ?? 0), $securityTargets))
        : 0,
    'security_firewall_warnings' => $securityFirewallRuleEnabled
        ? count(array_filter($securityTargets, fn (array $target): bool => in_array($target['firewall_status'] ?? null, ['inactif', 'not_installed', null], true)))
        : 0,
    'active_alerts' => (int) ($alertCounts['total'] ?? 0),
    'critical_alerts' => (int) ($alertCounts['critical'] ?? 0),
    'hardware_temperature_max' => $maxHardwareTemperature,
    'hardware_temperature_warning' => count($warningHardwareTargets),
    'hardware_temperature_critical' => count($criticalHardwareTargets),
    'smart_disks' => count($smartDisks),
    'smart_failures' => count($smartFailedDisks),
    'smart_media_errors' => count($smartMediaErrorDisks),
    'smart_wear_warning' => count($smartWearWarningDisks),
    'smart_wear_critical' => count($smartWearCriticalDisks),
    'smart_max_wear' => $smartMaxWear,
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

    if ($securityExposedPortsRuleEnabled && $exposedPorts > 0) {
        $priorities[] = [
            'score' => 3,
            'label' => $exposedPorts . ' port(s) expose(s)',
            'name' => $target['name'],
            'hostname' => $target['hostname'],
            'target_type' => $target['target_type'] ?? 'other',
            'href' => $baseUrl . 'pages/details-securite.php?id=' . (int) $target['id'],
            'badge' => 'bg-red-100 text-red-700',
        ];
    } elseif ($securityFirewallRuleEnabled && in_array($firewallStatus, ['inactif', 'not_installed', null], true)) {
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

foreach ($criticalHardwareTargets as $target) {
    $priorities[] = [
        'score' => 0,
        'label' => number_format((float) $target['max_temperature_celsius'], 1, ',', '') . " \u{00B0}C critique",
        'name' => $target['name'],
        'hostname' => $target['hostname'],
        'target_type' => $target['target_type'] ?? 'other',
        'href' => $baseUrl . 'pages/details-cible.php?id=' . (int) $target['id'],
        'badge' => 'bg-red-100 text-red-700',
    ];
}

foreach ($warningHardwareTargets as $target) {
    $priorities[] = [
        'score' => 3,
        'label' => number_format((float) $target['max_temperature_celsius'], 1, ',', '') . " \u{00B0}C elevee",
        'name' => $target['name'],
        'hostname' => $target['hostname'],
        'target_type' => $target['target_type'] ?? 'other',
        'href' => $baseUrl . 'pages/details-cible.php?id=' . (int) $target['id'],
        'badge' => 'bg-orange-100 text-orange-800',
    ];
}

foreach ($smartFailedDisks as $disk) {
    $priorities[] = [
        'score' => 0,
        'label' => 'SMART en echec ' . $disk['device_name'],
        'name' => $disk['server_name'],
        'hostname' => $disk['hostname'],
        'target_type' => $disk['target_type'] ?? 'other',
        'href' => $baseUrl . 'pages/details-cible.php?id=' . (int) $disk['server_id'],
        'badge' => 'bg-red-100 text-red-700',
    ];
}

foreach ($smartMediaErrorDisks as $disk) {
    $priorities[] = [
        'score' => 0,
        'label' => (int) $disk['media_errors'] . ' erreur(s) media',
        'name' => $disk['server_name'],
        'hostname' => $disk['hostname'],
        'target_type' => $disk['target_type'] ?? 'other',
        'href' => $baseUrl . 'pages/details-cible.php?id=' . (int) $disk['server_id'],
        'badge' => 'bg-red-100 text-red-700',
    ];
}

foreach ($smartWearCriticalDisks as $disk) {
    $priorities[] = [
        'score' => 0,
        'label' => number_format((float) $disk['percentage_used'], 1, ',', '') . ' % usure critique',
        'name' => $disk['server_name'],
        'hostname' => $disk['hostname'],
        'target_type' => $disk['target_type'] ?? 'other',
        'href' => $baseUrl . 'pages/details-cible.php?id=' . (int) $disk['server_id'],
        'badge' => 'bg-red-100 text-red-700',
    ];
}

foreach ($smartWearWarningDisks as $disk) {
    $priorities[] = [
        'score' => 3,
        'label' => number_format((float) $disk['percentage_used'], 1, ',', '') . ' % usure',
        'name' => $disk['server_name'],
        'hostname' => $disk['hostname'],
        'target_type' => $disk['target_type'] ?? 'other',
        'href' => $baseUrl . 'pages/details-cible.php?id=' . (int) $disk['server_id'],
        'badge' => 'bg-orange-100 text-orange-800',
    ];
}

usort($priorities, fn (array $a, array $b): int => $a['score'] <=> $b['score'] ?: strcasecmp($a['name'], $b['name']));
$priorities = array_slice($priorities, 0, 8);

function msmDashboardDuration(int $seconds): string
{
    if ($seconds < 120) {
        return $seconds . ' s';
    }

    $minutes = intdiv($seconds, 60);
    if ($minutes < 120) {
        return $minutes . ' min';
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    return $remainingMinutes > 0 ? $hours . ' h ' . $remainingMinutes . ' min' : $hours . ' h';
}

function msmDashboardScheduledChecks(SettingsManager $settingsManager, string $root): array
{
    $logsDirectory = $root . DIRECTORY_SEPARATOR . 'logs';
    $summary = [
        'total' => 0,
        'ok' => 0,
        'running' => 0,
        'stale' => 0,
        'error' => 0,
        'items' => [],
    ];

    foreach (SetupAssistant::checks() as $check) {
        $summary['total']++;

        $category = (string) ($check['settings_category'] ?? '');
        $status = $category !== '' ? (string) ($settingsManager->get($category, 'check_last_status') ?? '') : '';
        $lastAttempt = $category !== '' ? $settingsManager->get($category, 'check_last_attempt_at') : null;
        $scriptPath = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . $check['script'];
        $logPath = $logsDirectory . DIRECTORY_SEPARATOR . $check['log'];
        $state = 'ok';
        $reason = 'OK';

        if (!is_file($scriptPath)) {
            $state = 'error';
            $reason = 'script absent';
        } elseif ($status === 'running') {
            $state = 'running';
            $reason = 'en cours';
        } elseif ($status === 'error') {
            $state = 'error';
            $reason = 'derniere execution en erreur';
        } elseif (!is_file($logPath)) {
            $state = 'stale';
            $reason = 'log absent';
        } else {
            $mtime = filemtime($logPath);
            $staleAfterMinutes = (int) ($check['log_stale_after_minutes'] ?? 0);
            $lastAttemptAgeSeconds = msmDashboardDateAgeSeconds($lastAttempt);
            if ($staleAfterMinutes > 0 && $lastAttemptAgeSeconds !== null && $lastAttemptAgeSeconds > $staleAfterMinutes * 60) {
                $state = 'stale';
                $reason = 'derniere tentative ancienne de ' . msmDashboardDuration($lastAttemptAgeSeconds);
            } elseif ($mtime !== false && $staleAfterMinutes > 0 && $lastAttemptAgeSeconds === null) {
                $logAgeSeconds = time() - $mtime;
                if ($logAgeSeconds > $staleAfterMinutes * 60) {
                    $state = 'stale';
                    $reason = 'log ancien de ' . msmDashboardDuration($logAgeSeconds);
                }
            }
        }

        $summary[$state]++;
        if (!in_array($state, ['ok', 'running'], true)) {
            $summary['items'][] = [
                'name' => $check['name'],
                'state' => $state,
                'reason' => $reason,
            ];
        }
    }

    return $summary;
}

function msmDashboardDateAgeSeconds(?string $date): ?int
{
    if ($date === null || trim($date) === '') {
        return null;
    }

    try {
        return time() - (new DateTimeImmutable($date))->getTimestamp();
    } catch (Throwable) {
        return null;
    }
}

$scheduledChecks = msmDashboardScheduledChecks($settingsManager, __DIR__);
$scheduledChecksState = $scheduledChecks['error'] > 0
    ? ['label' => 'Erreur', 'class' => 'border-red-200 bg-red-50 text-red-700', 'icon' => 'circle-alert']
    : ($scheduledChecks['stale'] > 0
        ? ['label' => 'Attention', 'class' => 'border-yellow-200 bg-yellow-50 text-yellow-800', 'icon' => 'triangle-alert']
        : ($scheduledChecks['running'] > 0
            ? ['label' => 'En cours', 'class' => 'border-blue-200 bg-blue-50 text-blue-700', 'icon' => 'loader-circle']
            : ['label' => 'OK', 'class' => 'border-green-200 bg-green-50 text-green-700', 'icon' => 'circle-check']));
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
                <div class="text-xs font-semibold uppercase text-slate-500">Alertes a traiter</div>
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

        <a href="<?= $baseUrl ?>pages/serveurs.php" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-300">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase text-slate-500">Temperatures</div>
                <i data-lucide="thermometer" class="h-5 w-5 <?= $summary['hardware_temperature_critical'] > 0 ? 'text-red-500' : ($summary['hardware_temperature_warning'] > 0 ? 'text-orange-500' : 'text-green-600') ?>"></i>
            </div>
            <div class="mt-2 text-3xl font-bold <?= $summary['hardware_temperature_critical'] > 0 ? 'text-red-700' : ($summary['hardware_temperature_warning'] > 0 ? 'text-orange-700' : 'text-slate-900') ?>">
                <?= $summary['hardware_temperature_max'] !== null
                    ? htmlspecialchars(number_format((float) $summary['hardware_temperature_max'], 1, ',', '')) . ' &deg;C'
                    : '-' ?>
            </div>
            <div class="mt-1 text-xs text-slate-500">
                <?= $summary['hardware_temperature_critical'] ?> critique(s),
                <?= $summary['hardware_temperature_warning'] ?> warning(s)
            </div>
        </a>

        <a href="<?= $baseUrl ?>pages/serveurs.php" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-blue-300">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase text-slate-500">Disques SMART</div>
                <i data-lucide="hard-drive" class="h-5 w-5 <?= ($summary['smart_failures'] + $summary['smart_media_errors'] + $summary['smart_wear_critical']) > 0 ? 'text-red-500' : ($summary['smart_wear_warning'] > 0 ? 'text-orange-500' : 'text-green-600') ?>"></i>
            </div>
            <div class="mt-2 text-3xl font-bold <?= ($summary['smart_failures'] + $summary['smart_media_errors'] + $summary['smart_wear_critical']) > 0 ? 'text-red-700' : ($summary['smart_wear_warning'] > 0 ? 'text-orange-700' : 'text-slate-900') ?>">
                <?= $summary['smart_disks'] ?>
            </div>
            <div class="mt-1 text-xs text-slate-500">
                <?= $summary['smart_failures'] ?> echec(s),
                <?= $summary['smart_media_errors'] ?> avec erreurs media
                <?php if ($summary['smart_max_wear'] !== null): ?>
                    &middot; usure max <?= htmlspecialchars(number_format((float) $summary['smart_max_wear'], 1, ',', '')) ?> %
                <?php endif; ?>
            </div>
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
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Checks planifies</h2>
                    <p class="mt-1 text-sm text-slate-500">Synthese des scripts, logs et derniers statuts connus.</p>
                </div>
                <span class="inline-flex items-center gap-2 rounded border px-3 py-1.5 text-sm font-semibold <?= htmlspecialchars($scheduledChecksState['class']) ?>">
                    <i data-lucide="<?= htmlspecialchars($scheduledChecksState['icon']) ?>" class="h-4 w-4"></i>
                    <?= htmlspecialchars($scheduledChecksState['label']) ?>
                </span>
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded border border-green-100 bg-green-50 p-3">
                    <div class="text-xs font-semibold uppercase text-green-700">OK</div>
                    <div class="mt-1 text-2xl font-bold text-green-800"><?= (int) $scheduledChecks['ok'] ?></div>
                </div>
                <div class="rounded border border-blue-100 bg-blue-50 p-3">
                    <div class="text-xs font-semibold uppercase text-blue-700">En cours</div>
                    <div class="mt-1 text-2xl font-bold text-blue-800"><?= (int) $scheduledChecks['running'] ?></div>
                </div>
                <div class="rounded border border-yellow-100 bg-yellow-50 p-3">
                    <div class="text-xs font-semibold uppercase text-yellow-700">Retard</div>
                    <div class="mt-1 text-2xl font-bold text-yellow-800"><?= (int) $scheduledChecks['stale'] ?></div>
                </div>
                <div class="rounded border border-red-100 bg-red-50 p-3">
                    <div class="text-xs font-semibold uppercase text-red-700">Erreur</div>
                    <div class="mt-1 text-2xl font-bold text-red-800"><?= (int) $scheduledChecks['error'] ?></div>
                </div>
            </div>

            <?php if (!empty($scheduledChecks['items'])): ?>
                <div class="mt-4 divide-y divide-gray-100 rounded border border-gray-200">
                    <?php foreach (array_slice($scheduledChecks['items'], 0, 4) as $item): ?>
                        <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                            <div class="font-semibold text-slate-800"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="<?= $item['state'] === 'error' ? 'text-red-700' : 'text-yellow-800' ?>">
                                <?= htmlspecialchars($item['reason']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="mt-4 rounded border border-green-200 bg-green-50 px-3 py-3 text-sm font-semibold text-green-700">
                    Tous les checks planifies sont a jour.
                </p>
            <?php endif; ?>

            <div class="mt-4 grid grid-cols-2 gap-2">
                <a href="<?= $baseUrl ?>pages/collectors.php" class="inline-flex items-center justify-center gap-2 rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-gray-50">
                    <i data-lucide="workflow" class="h-4 w-4"></i>
                    Collecteurs
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
