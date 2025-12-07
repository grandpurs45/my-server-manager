<?php

/**
 * Construit la liste des alertes de supervision à partir des enregistrements de la table `servers`.
 *
 * @param array $servers Liste des serveurs (fetchAll(PDO::FETCH_ASSOC)).
 * @return array Liste des alertes. Chaque alerte contient :
 *               - 'server'  => tableau du serveur
 *               - 'level'   => 'critical' | 'warning' | 'info'
 *               - 'reason'  => courte cause ('Ping KO', 'SSH KO', ...)
 *               - 'message' => message lisible
 */
function msm_build_supervision_alerts(array $servers): array
{
    $alerts = [];

    foreach ($servers as $server) {
        $serverAlerts = [];

        // 1) Serveur DOWN (ping KO)
        if (($server['status'] ?? null) !== 'up') {
            $serverAlerts[] = [
                'level'   => 'critical',
                'reason'  => 'Ping KO',
                'message' => 'Serveur injoignable',
            ];
        }

        // 2) SSH KO alors qu’il est activé
        if (
            isset($server['ssh_enabled'], $server['ssh_status'])
            && (int)$server['ssh_enabled'] === 1
            && $server['ssh_status'] !== 'success'
        ) {
            $serverAlerts[] = [
                'level'   => 'warning',
                'reason'  => 'SSH KO',
                'message' => 'Connexion SSH impossible',
            ];
        }

        // 3) Latence élevée (> 100 ms) pour un serveur UP
        if (
            ($server['status'] ?? null) === 'up'
            && isset($server['latency'])
            && $server['latency'] !== null
            && (int)$server['latency'] > 100
        ) {
            $serverAlerts[] = [
                'level'   => 'warning',
                'reason'  => 'Latence élevée',
                'message' => 'Latence ' . (int)$server['latency'] . ' ms',
            ];
        }

        foreach ($serverAlerts as $alert) {
            $alerts[] = [
                'server'  => $server,
                'level'   => $alert['level'],
                'reason'  => $alert['reason'],
                'message' => $alert['message'],
            ];
        }
    }

    return $alerts;
}
