<?php
namespace MSM;

class ServerCheckHistoryRepository
{
    private ?bool $tableAvailable = null;

    public function __construct(private \PDO $pdo)
    {
    }

    public function recordChange(
        int $serverId,
        string $eventType,
        ?string $previousValue,
        ?string $newValue,
        string $message,
        string $createdAt
    ): void {
        if ($previousValue === $newValue || !$this->tableExists()) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO server_check_events (
                server_id,
                event_type,
                previous_value,
                new_value,
                message,
                created_at
            ) VALUES (
                :server_id,
                :event_type,
                :previous_value,
                :new_value,
                :message,
                :created_at
            )
        ");
        $stmt->execute([
            ':server_id' => $serverId,
            ':event_type' => $eventType,
            ':previous_value' => $previousValue,
            ':new_value' => $newValue,
            ':message' => $message,
            ':created_at' => $createdAt,
        ]);
    }

    public function latestForServer(int $serverId, int $limit = 10): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare("
            SELECT event_type, previous_value, new_value, message, created_at
            FROM server_check_events
            WHERE server_id = :server_id
            ORDER BY created_at DESC, id DESC
            LIMIT {$limit}
        ");
        $stmt->execute([':server_id' => $serverId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function tableExists(): bool
    {
        if ($this->tableAvailable !== null) {
            return $this->tableAvailable;
        }

        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'server_check_events'");
            $this->tableAvailable = $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            $this->tableAvailable = false;
        }

        return $this->tableAvailable;
    }
}
