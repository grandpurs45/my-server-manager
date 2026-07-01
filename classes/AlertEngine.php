<?php
namespace MSM;

use PDO;

class AlertEngine
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly AlertRepository $repository
    ) {
    }

    public function run(): array
    {
        $rules = $this->repository->getEnabledRules();
        $candidates = [];

        if ($rules === []) {
            return ['opened' => 0, 'updated' => 0, 'resolved' => 0, 'active' => 0];
        }

        $candidates = array_merge(
            $candidates,
            $this->evaluateSupervision($rules),
            $this->evaluatePatchManagement($rules),
            $this->evaluateOsLifecycle($rules),
            $this->evaluateSecurity($rules),
            $this->evaluateHardwareHealth($rules),
            $this->evaluateHardwareSmart($rules),
            $this->evaluateHardwareRuntime($rules),
            $this->evaluateHomeAssistant($rules)
        );

        return $this->repository->syncCandidates($candidates, array_keys($rules));
    }

    private function evaluateSupervision(array $rules): array
    {
        $candidates = [];
        $stmt = $this->pdo->query("
            SELECT id, name, hostname, status, ssh_enabled, ssh_status, last_check,
                   latency, ping_loss_percent, ping_packets_sent, ping_packets_received,
                   TIMESTAMPDIFF(MINUTE, last_check, NOW()) AS last_check_age_minutes
            FROM servers
            ORDER BY name ASC
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $server) {
            $serverId = (int) $server['id'];
            $name = $server['name'] ?? 'Serveur inconnu';
            $hostname = $server['hostname'] ?? '';

            if (isset($rules['server_down']) && ($server['status'] ?? '') !== 'up') {
                $candidates[] = $this->candidate(
                    'server_down',
                    $serverId,
                    $rules['server_down']['severity'] ?? 'critical',
                    $name . ' est down',
                    'Le dernier statut supervision indique que la cible est injoignable.',
                    'server_down:' . $serverId
                );
            }

            if (($server['status'] ?? '') === 'up' && isset($rules['ping_packet_loss'])) {
                $threshold = max(1, (int) ($rules['ping_packet_loss']['threshold_value'] ?? 25));
                $loss = $server['ping_loss_percent'] !== null ? (float) $server['ping_loss_percent'] : null;
                if ($loss !== null && $loss >= $threshold) {
                    $received = $server['ping_packets_received'] !== null ? (int) $server['ping_packets_received'] : null;
                    $sent = $server['ping_packets_sent'] !== null ? (int) $server['ping_packets_sent'] : null;
                    $message = 'Le dernier check ping indique ' . number_format($loss, 1, ',', '')
                        . ' % de perte, pour un seuil de ' . $threshold . ' %.';
                    if ($received !== null && $sent !== null) {
                        $message .= ' Paquets recus/envoyes : ' . $received . '/' . $sent . '.';
                    }

                    $candidates[] = $this->candidate(
                        'ping_packet_loss',
                        $serverId,
                        $rules['ping_packet_loss']['severity'] ?? 'warning',
                        'Perte de ping sur ' . $name,
                        $message,
                        'ping_packet_loss:' . $serverId
                    );
                }
            }

            if (($server['status'] ?? '') === 'up' && isset($rules['ping_latency_high'])) {
                $threshold = max(1, (int) ($rules['ping_latency_high']['threshold_value'] ?? 100));
                $latency = $server['latency'] !== null ? (int) $server['latency'] : null;
                if ($latency !== null && $latency >= $threshold) {
                    $candidates[] = $this->candidate(
                        'ping_latency_high',
                        $serverId,
                        $rules['ping_latency_high']['severity'] ?? 'warning',
                        'Latence ping elevee sur ' . $name,
                        'La latence moyenne du dernier ping est de ' . $latency
                            . ' ms, pour un seuil de ' . $threshold . ' ms.',
                        'ping_latency_high:' . $serverId
                    );
                }
            }

            if (isset($rules['ssh_failed'])
                && (int) ($server['ssh_enabled'] ?? 0) === 1
                && ($server['ssh_status'] ?? '') !== 'success'
            ) {
                $candidates[] = $this->candidate(
                    'ssh_failed',
                    $serverId,
                    $rules['ssh_failed']['severity'] ?? 'warning',
                    'SSH KO sur ' . $name,
                    'La connexion SSH echoue pour ' . ($hostname !== '' ? $hostname : $name) . '.',
                    'ssh_failed:' . $serverId
                );
            }

            if (isset($rules['stale_supervision_check'])) {
                $threshold = (int) ($rules['stale_supervision_check']['threshold_value'] ?? 30);
                $age = $server['last_check_age_minutes'] !== null ? (int) $server['last_check_age_minutes'] : null;

                if ($server['last_check'] === null || ($age !== null && $age > $threshold)) {
                    $message = $server['last_check'] === null
                        ? 'Aucun check supervision connu.'
                        : 'Le dernier check supervision date de ' . $age . ' minutes.';

                    $candidates[] = $this->candidate(
                        'stale_supervision_check',
                        $serverId,
                        $rules['stale_supervision_check']['severity'] ?? 'warning',
                        'Check supervision ancien sur ' . $name,
                        $message,
                        'stale_supervision_check:' . $serverId
                    );
                }
            }
        }

        return $candidates;
    }

    private function evaluatePatchManagement(array $rules): array
    {
        if (!$this->tableExists('patch_checks')) {
            return [];
        }

        $candidates = [];
        $stmt = $this->pdo->query("
            SELECT
                s.id,
                s.name,
                s.hostname,
                pc.security_updates_count,
                pc.reboot_required
            FROM servers s
            INNER JOIN patch_checks pc
                ON pc.id = (
                    SELECT pc2.id
                    FROM patch_checks pc2
                    WHERE pc2.server_id = s.id
                    ORDER BY pc2.checked_at DESC, pc2.id DESC
                    LIMIT 1
                )
            WHERE s.patch_management_enabled = 1
            ORDER BY s.name ASC
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $target) {
            $serverId = (int) $target['id'];
            $name = $target['name'] ?? 'Serveur inconnu';
            $securityUpdates = (int) ($target['security_updates_count'] ?? 0);

            if (isset($rules['patch_security_updates']) && $securityUpdates > 0) {
                $candidates[] = $this->candidate(
                    'patch_security_updates',
                    $serverId,
                    $rules['patch_security_updates']['severity'] ?? 'warning',
                    'Mises a jour securite sur ' . $name,
                    $securityUpdates . ' mise(s) a jour de securite disponible(s).',
                    'patch_security_updates:' . $serverId
                );
            }

            if (isset($rules['reboot_required']) && !empty($target['reboot_required'])) {
                $candidates[] = $this->candidate(
                    'reboot_required',
                    $serverId,
                    $rules['reboot_required']['severity'] ?? 'warning',
                    'Reboot requis sur ' . $name,
                    'Le dernier check Patch Management indique qu un redemarrage est requis.',
                    'reboot_required:' . $serverId
                );
            }
        }

        return $candidates;
    }

    private function evaluateOsLifecycle(array $rules): array
    {
        if (!$this->tableExists('os_lifecycle_checks')) {
            return [];
        }

        $candidates = [];
        $stmt = $this->pdo->query("
            SELECT
                s.id,
                s.name,
                olc.os_family,
                olc.os_version,
                olc.support_status,
                olc.support_ends_at
            FROM servers s
            INNER JOIN os_lifecycle_checks olc
                ON olc.id = (
                    SELECT olc2.id
                    FROM os_lifecycle_checks olc2
                    WHERE olc2.server_id = s.id
                    ORDER BY olc2.checked_at DESC, olc2.id DESC
                    LIMIT 1
                )
            ORDER BY s.name ASC
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $target) {
            $serverId = (int) $target['id'];
            $name = $target['name'] ?? 'Serveur inconnu';
            $osLabel = trim(($target['os_family'] ?? 'OS') . ' ' . ($target['os_version'] ?? ''));
            $supportStatus = $target['support_status'] ?? null;
            $hasDetectedOs = !empty($target['os_family']) || !empty($target['os_version']);

            if ($supportStatus === 'eol' && isset($rules['os_eol'])) {
                $candidates[] = $this->candidate(
                    'os_eol',
                    $serverId,
                    $rules['os_eol']['severity'] ?? 'critical',
                    'OS obsolete sur ' . $name,
                    $osLabel . ' n est plus supporte.',
                    'os_eol:' . $serverId
                );
            }

            if ($supportStatus === 'eol_soon' && isset($rules['os_eol_soon'])) {
                $message = $osLabel . ' arrive en fin de support.';
                if (!empty($target['support_ends_at'])) {
                    $message .= ' Fin connue : ' . $target['support_ends_at'] . '.';
                }

                $candidates[] = $this->candidate(
                    'os_eol_soon',
                    $serverId,
                    $rules['os_eol_soon']['severity'] ?? 'warning',
                    'Fin de support proche sur ' . $name,
                    $message,
                    'os_eol_soon:' . $serverId
                );
            }

            if ($supportStatus === 'unknown' && $hasDetectedOs && isset($rules['os_lifecycle_unknown'])) {
                $candidates[] = $this->candidate(
                    'os_lifecycle_unknown',
                    $serverId,
                    $rules['os_lifecycle_unknown']['severity'] ?? 'info',
                    'Cycle de vie OS inconnu sur ' . $name,
                    'Aucune date de fin de support connue pour ' . $osLabel . '.',
                    'os_lifecycle_unknown:' . $serverId
                );
            }
        }

        return $candidates;
    }

    private function evaluateSecurity(array $rules): array
    {
        if (!$this->tableExists('security_checks')) {
            return [];
        }

        $candidates = [];
        $stmt = $this->pdo->query("
            SELECT
                s.id,
                s.name,
                sc.exposed_ports_count,
                sc.firewall_status
            FROM servers s
            INNER JOIN security_checks sc
                ON sc.id = (
                    SELECT sc2.id
                    FROM security_checks sc2
                    WHERE sc2.server_id = s.id
                    ORDER BY sc2.checked_at DESC, sc2.id DESC
                    LIMIT 1
                )
            WHERE s.security_enabled = 1
            ORDER BY s.name ASC
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $target) {
            $serverId = (int) $target['id'];
            $name = $target['name'] ?? 'Serveur inconnu';
            $exposedPorts = (int) ($target['exposed_ports_count'] ?? 0);
            $firewallStatus = $target['firewall_status'] ?? null;

            if (isset($rules['security_exposed_ports']) && $exposedPorts > 0) {
                $candidates[] = $this->candidate(
                    'security_exposed_ports',
                    $serverId,
                    $rules['security_exposed_ports']['severity'] ?? 'warning',
                    'Ports exposes sur ' . $name,
                    $exposedPorts . ' port(s) expose(s) detecte(s) par le dernier check securite.',
                    'security_exposed_ports:' . $serverId
                );
            }

            if (isset($rules['security_firewall_disabled']) && in_array($firewallStatus, ['inactif', 'not_installed', null], true)) {
                $statusLabel = $firewallStatus ?: 'inconnu';
                $candidates[] = $this->candidate(
                    'security_firewall_disabled',
                    $serverId,
                    $rules['security_firewall_disabled']['severity'] ?? 'warning',
                    'Firewall a verifier sur ' . $name,
                    'Le dernier check securite indique un firewall ' . $statusLabel . '.',
                    'security_firewall_disabled:' . $serverId
                );
            }
        }

        return $candidates;
    }

    private function evaluateHardwareHealth(array $rules): array
    {
        if (!$this->tableExists('hardware_health_checks')) {
            return [];
        }

        $warningRule = $rules['hardware_temperature_warning'] ?? null;
        $criticalRule = $rules['hardware_temperature_critical'] ?? null;
        if ($warningRule === null && $criticalRule === null) {
            return [];
        }

        $warningThreshold = max(1, (int) ($warningRule['threshold_value'] ?? 70));
        $criticalThreshold = max(1, (int) ($criticalRule['threshold_value'] ?? 85));
        $candidates = [];

        $stmt = $this->pdo->query("
            SELECT
                s.id,
                s.name,
                s.hardware_profile,
                hc.status,
                hc.max_temperature_celsius,
                hc.checked_at
            FROM servers s
            INNER JOIN hardware_health_checks hc
                ON hc.id = (
                    SELECT hc2.id
                    FROM hardware_health_checks hc2
                    WHERE hc2.server_id = s.id
                    ORDER BY hc2.checked_at DESC, hc2.id DESC
                    LIMIT 1
                )
            WHERE s.hardware_profile IN ('physical', 'appliance')
            ORDER BY s.name ASC
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $target) {
            if (($target['status'] ?? '') !== 'ok' || $target['max_temperature_celsius'] === null) {
                continue;
            }

            $serverId = (int) $target['id'];
            $name = $target['name'] ?? 'Serveur inconnu';
            $temperature = (float) $target['max_temperature_celsius'];
            $formattedTemperature = number_format($temperature, 1, ',', '');

            if ($criticalRule !== null && $temperature >= $criticalThreshold) {
                $candidates[] = $this->candidate(
                    'hardware_temperature_critical',
                    $serverId,
                    $criticalRule['severity'] ?? 'critical',
                    'Temperature critique sur ' . $name,
                    'La temperature materielle maximale est de ' . $formattedTemperature
                        . " \u{00B0}C, pour un seuil critique de " . $criticalThreshold . " \u{00B0}C.",
                    'hardware_temperature_critical:' . $serverId
                );
                continue;
            }

            if ($warningRule !== null && $temperature >= $warningThreshold) {
                $candidates[] = $this->candidate(
                    'hardware_temperature_warning',
                    $serverId,
                    $warningRule['severity'] ?? 'warning',
                    'Temperature elevee sur ' . $name,
                    'La temperature materielle maximale est de ' . $formattedTemperature
                        . " \u{00B0}C, pour un seuil warning de " . $warningThreshold . " \u{00B0}C.",
                    'hardware_temperature_warning:' . $serverId
                );
            }
        }

        return $candidates;
    }

    private function evaluateHardwareSmart(array $rules): array
    {
        if (!$this->tableExists('hardware_health_checks') || !$this->tableExists('hardware_smart_disks')) {
            return [];
        }

        $failedRule = $rules['hardware_smart_failed'] ?? null;
        $mediaRule = $rules['hardware_smart_media_errors'] ?? null;
        $wearWarningRule = $rules['hardware_smart_wear_warning'] ?? null;
        $wearCriticalRule = $rules['hardware_smart_wear_critical'] ?? null;

        if ($failedRule === null && $mediaRule === null && $wearWarningRule === null && $wearCriticalRule === null) {
            return [];
        }

        $mediaThreshold = max(1, (int) ($mediaRule['threshold_value'] ?? 1));
        $wearWarningThreshold = max(1, (int) ($wearWarningRule['threshold_value'] ?? 80));
        $wearCriticalThreshold = max(1, (int) ($wearCriticalRule['threshold_value'] ?? 95));
        $candidates = [];

        $stmt = $this->pdo->query("
            SELECT
                s.id AS server_id,
                s.name AS server_name,
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
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $disk) {
            $serverId = (int) $disk['server_id'];
            $serverName = $disk['server_name'] ?? 'Serveur inconnu';
            $device = $disk['device_name'] ?? 'disque inconnu';
            $model = trim((string) ($disk['model_name'] ?? ''));
            $diskLabel = $device . ($model !== '' ? ' (' . $model . ')' : '');
            $fingerprintSuffix = $serverId . ':' . $device;

            if ($failedRule !== null && $disk['smart_passed'] !== null && (int) $disk['smart_passed'] === 0) {
                $candidates[] = $this->candidate(
                    'hardware_smart_failed',
                    $serverId,
                    $failedRule['severity'] ?? 'critical',
                    'SMART en echec sur ' . $serverName,
                    'Le test de sante SMART global est en echec pour ' . $diskLabel . '.',
                    'hardware_smart_failed:' . $fingerprintSuffix
                );
            }

            $mediaErrors = $disk['media_errors'] !== null ? (int) $disk['media_errors'] : null;
            if ($mediaRule !== null && $mediaErrors !== null && $mediaErrors >= $mediaThreshold) {
                $candidates[] = $this->candidate(
                    'hardware_smart_media_errors',
                    $serverId,
                    $mediaRule['severity'] ?? 'critical',
                    'Erreurs media sur ' . $serverName,
                    $diskLabel . ' remonte ' . $mediaErrors
                        . ' erreur(s) media, pour un seuil de ' . $mediaThreshold . '.',
                    'hardware_smart_media_errors:' . $fingerprintSuffix
                );
            }

            if ($disk['percentage_used'] === null) {
                continue;
            }

            $wear = (float) $disk['percentage_used'];
            $formattedWear = number_format($wear, 1, ',', '');

            if ($wearCriticalRule !== null && $wear >= $wearCriticalThreshold) {
                $candidates[] = $this->candidate(
                    'hardware_smart_wear_critical',
                    $serverId,
                    $wearCriticalRule['severity'] ?? 'critical',
                    'Usure disque critique sur ' . $serverName,
                    $diskLabel . ' indique ' . $formattedWear
                        . ' % d usure, pour un seuil critique de ' . $wearCriticalThreshold . ' %.',
                    'hardware_smart_wear_critical:' . $fingerprintSuffix
                );
                continue;
            }

            if ($wearWarningRule !== null && $wear >= $wearWarningThreshold) {
                $candidates[] = $this->candidate(
                    'hardware_smart_wear_warning',
                    $serverId,
                    $wearWarningRule['severity'] ?? 'warning',
                    'Usure disque elevee sur ' . $serverName,
                    $diskLabel . ' indique ' . $formattedWear
                        . ' % d usure, pour un seuil warning de ' . $wearWarningThreshold . ' %.',
                    'hardware_smart_wear_warning:' . $fingerprintSuffix
                );
            }
        }

        return $candidates;
    }

    private function evaluateHardwareRuntime(array $rules): array
    {
        $rule = $rules['stale_hardware_health_check'] ?? null;
        if ($rule === null) {
            return [];
        }

        $eligibleTargets = (int) $this->pdo->query("
            SELECT COUNT(*)
            FROM servers
            WHERE hardware_profile IN ('physical', 'appliance')
        ")->fetchColumn();
        if ($eligibleTargets === 0) {
            return [];
        }

        $thresholdMinutes = max(1, (int) ($rule['threshold_value'] ?? 45));
        $stmt = $this->pdo->prepare("
            SELECT setting_value
            FROM settings
            WHERE category = 'hardware_health'
              AND setting_key = 'check_last_run_at'
        ");
        $stmt->execute();
        $lastRun = $stmt->fetchColumn();

        if ($lastRun === false || trim((string) $lastRun) === '') {
            return [
                $this->candidate(
                    'stale_hardware_health_check',
                    null,
                    $rule['severity'] ?? 'warning',
                    'Check materiel jamais execute',
                    'Au moins une cible materielle est active, mais aucun check materiel termine n est connu.',
                    'stale_hardware_health_check:global'
                ),
            ];
        }

        try {
            $lastRunAt = new \DateTimeImmutable((string) $lastRun);
            $ageMinutes = (int) floor((time() - $lastRunAt->getTimestamp()) / 60);
        } catch (\Throwable) {
            $ageMinutes = $thresholdMinutes + 1;
        }

        if ($ageMinutes <= $thresholdMinutes) {
            return [];
        }

        return [
            $this->candidate(
                'stale_hardware_health_check',
                null,
                $rule['severity'] ?? 'warning',
                'Check materiel trop ancien',
                'Le dernier check materiel termine date de ' . $ageMinutes
                    . ' minutes, pour un seuil de ' . $thresholdMinutes . ' minutes.',
                'stale_hardware_health_check:global'
            ),
        ];
    }

    private function evaluateHomeAssistant(array $rules): array
    {
        if (!$this->tableExists('home_assistant_checks')) {
            return [];
        }

        $errorRule = $rules['home_assistant_check_error'] ?? null;
        $staleRule = $rules['home_assistant_check_stale'] ?? null;
        $coreUpdateRule = $rules['home_assistant_core_update_available'] ?? null;
        $supervisorUpdateRule = $rules['home_assistant_supervisor_update_available'] ?? null;
        $osUpdateRule = $rules['home_assistant_os_update_available'] ?? null;

        if ($errorRule === null
            && $staleRule === null
            && $coreUpdateRule === null
            && $supervisorUpdateRule === null
            && $osUpdateRule === null
        ) {
            return [];
        }

        $staleThreshold = max(1, (int) ($staleRule['threshold_value'] ?? 60));
        $candidates = [];

        $stmt = $this->pdo->query("
            SELECT
                s.id,
                s.name,
                s.hostname,
                hac.status,
                hac.ha_version,
                hac.ha_latest_version,
                hac.ha_update_available,
                hac.supervisor_version,
                hac.supervisor_latest_version,
                hac.supervisor_update_available,
                hac.os_version,
                hac.os_latest_version,
                hac.os_update_available,
                hac.checked_at,
                hac.error_message,
                TIMESTAMPDIFF(MINUTE, hac.checked_at, NOW()) AS check_age_minutes
            FROM servers s
            LEFT JOIN home_assistant_checks hac
                ON hac.id = (
                    SELECT hac2.id
                    FROM home_assistant_checks hac2
                    WHERE hac2.server_id = s.id
                    ORDER BY hac2.checked_at DESC, hac2.id DESC
                    LIMIT 1
                )
            WHERE s.target_type = 'home_assistant'
            ORDER BY s.name ASC
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $target) {
            $serverId = (int) $target['id'];
            $name = $target['name'] ?? 'Home Assistant';

            if ($staleRule !== null) {
                $age = $target['check_age_minutes'] !== null ? (int) $target['check_age_minutes'] : null;
                if ($target['checked_at'] === null || ($age !== null && $age > $staleThreshold)) {
                    $message = $target['checked_at'] === null
                        ? 'Aucun check Home Assistant connu pour cette cible.'
                        : 'Le dernier check Home Assistant date de ' . $age
                            . ' minutes, pour un seuil de ' . $staleThreshold . ' minutes.';

                    $candidates[] = $this->candidate(
                        'home_assistant_check_stale',
                        $serverId,
                        $staleRule['severity'] ?? 'warning',
                        'Check Home Assistant ancien sur ' . $name,
                        $message,
                        'home_assistant_check_stale:' . $serverId
                    );
                }
            }

            if ($target['checked_at'] === null) {
                continue;
            }

            if ($errorRule !== null && ($target['status'] ?? '') === 'error') {
                $message = 'Le dernier check Home Assistant est en erreur.';
                if (!empty($target['error_message'])) {
                    $message .= ' Message: ' . $target['error_message'];
                }

                $candidates[] = $this->candidate(
                    'home_assistant_check_error',
                    $serverId,
                    $errorRule['severity'] ?? 'warning',
                    'Check Home Assistant en erreur sur ' . $name,
                    $message,
                    'home_assistant_check_error:' . $serverId
                );
            }

            if ($coreUpdateRule !== null && (int) ($target['ha_update_available'] ?? 0) === 1) {
                $candidates[] = $this->candidate(
                    'home_assistant_core_update_available',
                    $serverId,
                    $coreUpdateRule['severity'] ?? 'warning',
                    'Update Home Assistant Core sur ' . $name,
                    $this->homeAssistantUpdateMessage('Core', $target['ha_version'] ?? null, $target['ha_latest_version'] ?? null),
                    'home_assistant_core_update_available:' . $serverId
                );
            }

            if ($supervisorUpdateRule !== null && (int) ($target['supervisor_update_available'] ?? 0) === 1) {
                $candidates[] = $this->candidate(
                    'home_assistant_supervisor_update_available',
                    $serverId,
                    $supervisorUpdateRule['severity'] ?? 'warning',
                    'Update Home Assistant Supervisor sur ' . $name,
                    $this->homeAssistantUpdateMessage('Supervisor', $target['supervisor_version'] ?? null, $target['supervisor_latest_version'] ?? null),
                    'home_assistant_supervisor_update_available:' . $serverId
                );
            }

            if ($osUpdateRule !== null && (int) ($target['os_update_available'] ?? 0) === 1) {
                $candidates[] = $this->candidate(
                    'home_assistant_os_update_available',
                    $serverId,
                    $osUpdateRule['severity'] ?? 'warning',
                    'Update Home Assistant OS sur ' . $name,
                    $this->homeAssistantUpdateMessage('OS', $target['os_version'] ?? null, $target['os_latest_version'] ?? null),
                    'home_assistant_os_update_available:' . $serverId
                );
            }
        }

        return $candidates;
    }

    private function homeAssistantUpdateMessage(string $component, ?string $currentVersion, ?string $latestVersion): string
    {
        $message = 'Une mise a jour Home Assistant ' . $component . ' est disponible.';

        if ($currentVersion !== null && trim($currentVersion) !== '') {
            $message .= ' Version actuelle : ' . $currentVersion . '.';
        }

        if ($latestVersion !== null && trim($latestVersion) !== '') {
            $message .= ' Derniere version : ' . $latestVersion . '.';
        }

        return $message;
    }

    private function candidate(string $ruleKey, ?int $serverId, string $severity, string $title, string $message, string $fingerprint): AlertCandidate
    {
        return new AlertCandidate($ruleKey, $serverId, $severity, $title, $message, $fingerprint);
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name'
        );
        $stmt->execute([':table_name' => $tableName]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
