<?php
namespace MSM;

use PDO;

class OsLifecycleManager
{
    private OsLifecycleRepository $repository;
    private LinuxOsLifecycleCollector $linuxCollector;

    public function __construct(private readonly PDO $pdo)
    {
        $this->repository = new OsLifecycleRepository($pdo);
        $this->linuxCollector = new LinuxOsLifecycleCollector($this->repository);
    }

    public function run(): void
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM servers
            WHERE ssh_enabled = 1
              AND target_type IN ('linux', 'proxmox')
            ORDER BY name ASC
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $server) {
            $this->checkServer($server);
        }
    }

    public function runForServerId(int $serverId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM servers
            WHERE id = :id
              AND ssh_enabled = 1
              AND target_type IN ('linux', 'proxmox')
        ");
        $stmt->execute([':id' => $serverId]);
        $server = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$server) {
            throw new \RuntimeException('Cible introuvable, SSH desactive ou type non supporte.');
        }

        return $this->checkServer($server);
    }

    private function checkServer(array $server): array
    {
        $result = $this->linuxCollector->collect($server);
        $this->repository->saveCheck($result);

        echo '[' . $server['hostname'] . '] os_lifecycle=' . ($result['support_status'] ?? 'unknown')
            . ' os=' . (($result['os_family'] ?? '-') . ' ' . ($result['os_version'] ?? '-'))
            . ' upgrade=' . (!empty($result['upgrade_available']) ? ($result['upgrade_target_version'] ?? 'yes') : 'no')
            . "\n";

        return $result;
    }
}
