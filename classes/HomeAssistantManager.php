<?php
namespace MSM;

use PDO;

class HomeAssistantManager
{
    private HomeAssistantRepository $repository;
    private HomeAssistantSshCollector $collector;

    public function __construct(private readonly PDO $pdo)
    {
        $this->repository = new HomeAssistantRepository($pdo);
        $this->collector = new HomeAssistantSshCollector();
    }

    public function run(): void
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM servers
            WHERE target_type = 'home_assistant'
            ORDER BY name ASC
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $server) {
            $this->checkServer($server);
        }
    }

    public function runForServerId(int $serverId): HomeAssistantCheckResult
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM servers
            WHERE id = :id
              AND target_type = 'home_assistant'
        ");
        $stmt->execute([':id' => $serverId]);
        $server = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$server) {
            throw new \RuntimeException('Cible Home Assistant introuvable.');
        }

        return $this->checkServer($server);
    }

    private function checkServer(array $server): HomeAssistantCheckResult
    {
        $result = $this->collector->collect($server);
        $this->repository->saveResult($result);

        echo '[' . $server['hostname'] . '] home_assistant_status=' . $result->status
            . ' collector=' . ($result->collector ?? '-')
            . ' ha_version=' . ($result->haVersion ?? '-')
            . ' update=' . ($result->hasUpdate() ? 'yes' : 'no')
            . "\n";

        return $result;
    }
}
