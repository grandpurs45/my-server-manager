<?php
class SettingsManager {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function get(string $category, string $key): ?string {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE category = ? AND setting_key = ?");
        $stmt->execute([$category, $key]);
        return $stmt->fetchColumn() ?: null;
    }

    public function set(string $category, string $key, string $value): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO settings (category, setting_key, setting_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$category, $key, $value]);
    }

    public function getAllByCategory(string $category): array {
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE category = ?");
        $stmt->execute([$category]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getAll(): array {
        $stmt = $this->pdo->query("SELECT category, setting_key, setting_value FROM settings ORDER BY category, setting_key");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['category']][$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }
}