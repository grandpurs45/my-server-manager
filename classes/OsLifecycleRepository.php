<?php
namespace MSM;

use PDO;

class OsLifecycleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findReference(?string $family, ?string $version, ?string $codename): ?array
    {
        $family = $this->normalize($family);
        $version = trim((string) $version);
        $codename = $this->normalize($codename);

        if ($family === null || $family === '') {
            return null;
        }

        foreach ($this->candidateVersions($version) as $candidateVersion) {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM os_lifecycle_references
                WHERE os_family = :family
                  AND os_version = :version
                LIMIT 1
            ");
            $stmt->execute([
                ':family' => $family,
                ':version' => $candidateVersion,
            ]);

            $reference = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($reference) {
                return $reference;
            }
        }

        if ($codename !== null && $codename !== '') {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM os_lifecycle_references
                WHERE os_family = :family
                  AND os_codename = :codename
                ORDER BY os_version DESC
                LIMIT 1
            ");
            $stmt->execute([
                ':family' => $family,
                ':codename' => $codename,
            ]);

            $reference = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($reference) {
                return $reference;
            }
        }

        return null;
    }

    public function saveCheck(array $check): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO os_lifecycle_checks (
                server_id,
                os_family,
                os_version,
                os_codename,
                os_pretty_name,
                support_status,
                support_ends_at,
                upgrade_available,
                upgrade_target_version,
                upgrade_target_label,
                checked_at,
                error_message
            ) VALUES (
                :server_id,
                :os_family,
                :os_version,
                :os_codename,
                :os_pretty_name,
                :support_status,
                :support_ends_at,
                :upgrade_available,
                :upgrade_target_version,
                :upgrade_target_label,
                :checked_at,
                :error_message
            )
        ");

        $stmt->execute([
            ':server_id' => (int) $check['server_id'],
            ':os_family' => $check['os_family'] ?? null,
            ':os_version' => $check['os_version'] ?? null,
            ':os_codename' => $check['os_codename'] ?? null,
            ':os_pretty_name' => $check['os_pretty_name'] ?? null,
            ':support_status' => $check['support_status'] ?? 'unknown',
            ':support_ends_at' => $check['support_ends_at'] ?? null,
            ':upgrade_available' => !empty($check['upgrade_available']) ? 1 : 0,
            ':upgrade_target_version' => $check['upgrade_target_version'] ?? null,
            ':upgrade_target_label' => $check['upgrade_target_label'] ?? null,
            ':checked_at' => $check['checked_at'] ?? date('Y-m-d H:i:s'),
            ':error_message' => $check['error_message'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function getLatestForServer(int $serverId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM os_lifecycle_checks
            WHERE server_id = :server_id
            ORDER BY checked_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([':server_id' => $serverId]);

        $check = $stmt->fetch(PDO::FETCH_ASSOC);

        return $check ?: null;
    }

    private function candidateVersions(string $version): array
    {
        $version = trim($version);
        if ($version === '') {
            return [];
        }

        $candidates = [$version];
        if (preg_match('/^(\d+)\.(\d+)/', $version, $matches)) {
            $candidates[] = $matches[1] . '.' . $matches[2];
            $candidates[] = $matches[1];
        } elseif (preg_match('/^(\d+)/', $version, $matches)) {
            $candidates[] = $matches[1];
        }

        return array_values(array_unique($candidates));
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        $value = str_replace([' ', '-'], '_', $value);

        return $value === '' ? null : $value;
    }
}
