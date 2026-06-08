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
            $this->evaluateSecurity($rules)
        );

        return $this->repository->syncCandidates($candidates, array_keys($rules));
    }

    private function evaluateSupervision(array $rules): array
    {
        $candidates = [];
        $stmt = $this->pdo->query("
            SELECT id, name, hostname, status, ssh_enabled, ssh_status, last_check,
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
