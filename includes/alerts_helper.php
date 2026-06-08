<?php

use MSM\AlertRepository;

function msm_get_active_alerts(PDO $pdo): array
{
    $repository = new AlertRepository($pdo);

    return $repository->getActiveAlerts();
}

function msm_build_supervision_alerts(array $servers): array
{
    $alerts = [];

    foreach ($servers as $server) {
        $serverAlerts = [];

        if (($server['status'] ?? null) !== 'up') {
            $serverAlerts[] = [
                'level' => 'critical',
                'reason' => 'Ping KO',
                'message' => 'Serveur injoignable',
            ];
        }

        if (
            isset($server['ssh_enabled'], $server['ssh_status'])
            && (int) $server['ssh_enabled'] === 1
            && $server['ssh_status'] !== 'success'
        ) {
            $serverAlerts[] = [
                'level' => 'warning',
                'reason' => 'SSH KO',
                'message' => 'Connexion SSH impossible',
            ];
        }

        if (
            ($server['status'] ?? null) === 'up'
            && isset($server['latency'])
            && $server['latency'] !== null
            && (int) $server['latency'] > 100
        ) {
            $serverAlerts[] = [
                'level' => 'warning',
                'reason' => 'Latence elevee',
                'message' => 'Latence ' . (int) $server['latency'] . ' ms',
            ];
        }

        foreach ($serverAlerts as $alert) {
            $alerts[] = [
                'server' => $server,
                'level' => $alert['level'],
                'reason' => $alert['reason'],
                'message' => $alert['message'],
            ];
        }
    }

    return $alerts;
}
