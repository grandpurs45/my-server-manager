<?php
namespace MSM;

use PDO;

class HardwareHealthManager
{
    private HardwareHealthRepository $repository;
    private LinuxHardwareHealthCollector $collector;

    public function __construct(private readonly PDO $pdo)
    {
        $this->repository = new HardwareHealthRepository($pdo);
        $this->collector = new LinuxHardwareHealthCollector();
    }

    public function run(): void
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM servers
            WHERE hardware_profile IN ('physical', 'appliance')
            ORDER BY name ASC
        ");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $server) {
            $this->checkServer($server);
        }
    }

    public function runForServerId(int $serverId): HardwareHealthCheckResult
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM servers
            WHERE id = :id
              AND hardware_profile IN ('physical', 'appliance')
        ");
        $stmt->execute([':id' => $serverId]);
        $server = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$server) {
            throw new \RuntimeException('Cible introuvable ou profil materiel non eligible.');
        }

        return $this->checkServer($server);
    }

    private function checkServer(array $server): HardwareHealthCheckResult
    {
        $result = $this->collector->collect($server);
        $this->repository->saveResult($result);

        echo '[' . $server['hostname'] . '] hardware_status=' . $result->status
            . ' collector=' . ($result->collector ?? '-')
            . ' sensors=' . count($result->temperatures)
            . ' smart_disks=' . count($result->smartDisks)
            . ' max_celsius=' . ($result->maxTemperature() ?? '-')
            . "\n";

        return $result;
    }
}
