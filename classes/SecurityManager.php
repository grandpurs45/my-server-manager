<?php
namespace MSM;

use PDO;

class SecurityManager
{
    private SecurityStatusRepository $repository;
    private SecurityAudit $audit;

    public function __construct(private readonly PDO $pdo)
    {
        $this->repository = new SecurityStatusRepository($pdo);
        $this->audit = new SecurityAudit();
    }

    public function run(): void
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM servers
            WHERE security_enabled = 1
              AND ssh_enabled = 1
            ORDER BY name ASC
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $server) {
            $result = $this->audit->collect($server);
            $this->repository->saveResult($result);

            echo '[' . $server['hostname'] . '] security_status=' . $result->status
                . ' open_ports=' . count($result->openPorts)
                . ' exposed_ports=' . $result->exposedPortsCount()
                . ' firewall=' . ($result->firewallStatus ?? 'unknown')
                . "\n";
        }
    }
}
