<?php
namespace MSM;

use PDO;

class SecurityManager
{
    private const SUPPORTED_TARGET_TYPES = ['linux', 'proxmox', 'docker'];

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
            $this->checkServer($server);
        }
    }

    public function runForServerId(int $serverId): SecurityCheckResult
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM servers
            WHERE id = :id
              AND security_enabled = 1
              AND ssh_enabled = 1
        ");
        $stmt->execute([':id' => $serverId]);
        $server = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$server) {
            throw new \RuntimeException('Cible introuvable, analyse securite desactivee ou SSH desactive.');
        }

        return $this->checkServer($server);
    }

    private function checkServer(array $server): SecurityCheckResult
    {
        if (!in_array($server['target_type'] ?? 'other', self::SUPPORTED_TARGET_TYPES, true)) {
            $result = new SecurityCheckResult(
                serverId: (int) $server['id'],
                status: 'unsupported',
                openPorts: [],
                firewallStatus: null,
                checkedAt: date('Y-m-d H:i:s'),
                durationMs: 0,
                errorMessage: 'Type de cible non supporte par le collecteur securite generique.'
            );
            $this->repository->saveResult($result);

            echo '[' . $server['hostname'] . '] security_status=unsupported reason=target_type'
                . "\n";

            return $result;
        }

        $result = $this->audit->collect($server);
        $this->repository->saveResult($result);

        echo '[' . $server['hostname'] . '] security_status=' . $result->status
            . ' open_ports=' . count($result->openPorts)
            . ' exposed_ports=' . $result->exposedPortsCount()
            . ' firewall=' . ($result->firewallStatus ?? 'unknown')
            . "\n";

        return $result;
    }
}
