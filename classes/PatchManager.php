<?php
namespace MSM;

use PDO;

class PatchManager
{
    private PatchStatusRepository $repository;
    private LinuxAptPatchCollector $linuxAptCollector;
    private LinuxDnfPatchCollector $linuxDnfCollector;

    public function __construct(private readonly PDO $pdo)
    {
        $this->repository = new PatchStatusRepository($pdo);
        $this->linuxAptCollector = new LinuxAptPatchCollector();
        $this->linuxDnfCollector = new LinuxDnfPatchCollector();
    }

    public function run(): void
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM servers
            WHERE patch_management_enabled = 1
            ORDER BY name ASC
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $server) {
            $type = $server['target_type'] ?? 'other';

            if (!in_array($type, ['linux', 'proxmox'], true)) {
                $result = new PatchCheckResult(
                    serverId: (int) $server['id'],
                    collector: null,
                    status: 'unsupported',
                    normalUpdatesCount: 0,
                    securityUpdatesCount: 0,
                    rebootRequired: false,
                    checkedAt: date('Y-m-d H:i:s'),
                    errorMessage: 'Type de cible non supporte par le collecteur initial.'
                );
            } else {
                $result = $this->linuxAptCollector->collect($server);

                if ($result->status === 'unsupported') {
                    $result = $this->linuxDnfCollector->collect($server);
                }
            }

            $this->repository->saveResult($result);
            echo '[' . $server['hostname'] . '] patch_status=' . $result->status
                . ' security=' . $result->securityUpdatesCount
                . ' normal=' . $result->normalUpdatesCount
                . ' reboot=' . ($result->rebootRequired ? 'yes' : 'no')
                . "\n";
        }
    }
}
