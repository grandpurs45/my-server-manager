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
                return $this->withCalculatedUpgradeTarget($reference);
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
                return $this->withCalculatedUpgradeTarget($reference);
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

    public function getReferences(): array
    {
        $stmt = $this->pdo->query("
            SELECT *
            FROM os_lifecycle_references
            ORDER BY os_family ASC, os_version ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReferencesWithUsage(): array
    {
        $references = $this->getReferences();
        if ($references === []) {
            return [];
        }

        $latestChecks = $this->latestChecksByServer();
        foreach ($references as &$reference) {
            $reference['servers_count'] = 0;
            foreach ($latestChecks as $check) {
                if ($this->referenceMatchesCheck($reference, $check)) {
                    $reference['servers_count']++;
                }
            }
            $reference = $this->withCalculatedUpgradeTarget($reference);
        }
        unset($reference);

        return $references;
    }

    public function getDetectedOsFamilies(): array
    {
        $families = [];
        foreach ($this->latestChecksByServer() as $check) {
            $family = $this->normalize($check['os_family'] ?? null);
            if ($family !== null && $family !== '') {
                $families[$family] = true;
            }
        }

        $families = array_keys($families);
        sort($families);

        return $families;
    }

    public function saveReference(array $reference): void
    {
        $family = $this->normalize($reference['os_family'] ?? null);
        $version = trim((string) ($reference['os_version'] ?? ''));

        if ($family === null || $family === '' || $version === '') {
            throw new \InvalidArgumentException('Famille OS et version sont obligatoires.');
        }

        $supportEndsAt = $this->normalizeDate($reference['support_ends_at'] ?? null);
        $upgradeTargetVersion = trim((string) ($reference['upgrade_target_version'] ?? ''));
        $upgradeTargetLabel = trim((string) ($reference['upgrade_target_label'] ?? ''));
        $source = trim((string) ($reference['source'] ?? ''));
        $notes = trim((string) ($reference['notes'] ?? ''));

        $stmt = $this->pdo->prepare("
            INSERT INTO os_lifecycle_references (
                os_family,
                os_version,
                os_codename,
                support_ends_at,
                upgrade_target_version,
                upgrade_target_label,
                source,
                notes
            ) VALUES (
                :os_family,
                :os_version,
                :os_codename,
                :support_ends_at,
                :upgrade_target_version,
                :upgrade_target_label,
                :source,
                :notes
            )
            ON DUPLICATE KEY UPDATE
                os_codename = VALUES(os_codename),
                support_ends_at = VALUES(support_ends_at),
                upgrade_target_version = VALUES(upgrade_target_version),
                upgrade_target_label = VALUES(upgrade_target_label),
                source = VALUES(source),
                notes = VALUES(notes)
        ");
        $stmt->execute([
            ':os_family' => $family,
            ':os_version' => $version,
            ':os_codename' => $this->normalize($reference['os_codename'] ?? null),
            ':support_ends_at' => $supportEndsAt,
            ':upgrade_target_version' => $upgradeTargetVersion !== '' ? $upgradeTargetVersion : null,
            ':upgrade_target_label' => $upgradeTargetLabel !== '' ? $upgradeTargetLabel : null,
            ':source' => $source !== '' ? $source : null,
            ':notes' => $notes !== '' ? $notes : null,
        ]);
    }

    public function deleteReference(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM os_lifecycle_references WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function upsertImportedReference(array $reference): void
    {
        $family = $this->normalize($reference['os_family'] ?? null);
        $version = trim((string) ($reference['os_version'] ?? ''));

        if ($family === null || $family === '' || $version === '') {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO os_lifecycle_references (
                os_family,
                os_version,
                os_codename,
                support_ends_at,
                source,
                notes
            ) VALUES (
                :os_family,
                :os_version,
                :os_codename,
                :support_ends_at,
                :source,
                :notes
            )
            ON DUPLICATE KEY UPDATE
                os_codename = COALESCE(VALUES(os_codename), os_codename),
                support_ends_at = VALUES(support_ends_at),
                source = VALUES(source),
                notes = VALUES(notes)
        ");
        $stmt->execute([
            ':os_family' => $family,
            ':os_version' => $version,
            ':os_codename' => $this->normalize($reference['os_codename'] ?? null),
            ':support_ends_at' => $this->normalizeDate($reference['support_ends_at'] ?? null),
            ':source' => $reference['source'] ?? null,
            ':notes' => $reference['notes'] ?? null,
        ]);
    }

    private function normalizeDate(?string $date): ?string
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($date))->format('Y-m-d');
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Date de fin de support invalide: ' . $date);
        }
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

    private function latestChecksByServer(): array
    {
        $stmt = $this->pdo->query("
            SELECT olc.server_id, olc.os_family, olc.os_version, olc.os_codename
            FROM os_lifecycle_checks olc
            INNER JOIN (
                SELECT server_id, MAX(id) AS latest_id
                FROM os_lifecycle_checks
                GROUP BY server_id
            ) latest ON latest.latest_id = olc.id
            WHERE olc.os_family IS NOT NULL
              AND olc.os_family <> ''
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function withCalculatedUpgradeTarget(array $reference): array
    {
        if (!empty($reference['upgrade_target_version'])) {
            $reference['upgrade_source'] = 'manual';

            return $reference;
        }

        $candidate = $this->findNextSupportedReference(
            (string) ($reference['os_family'] ?? ''),
            (string) ($reference['os_version'] ?? '')
        );

        if ($candidate === null) {
            $reference['upgrade_source'] = 'none';

            return $reference;
        }

        $reference['upgrade_target_version'] = $candidate['os_version'];
        $reference['upgrade_target_label'] = $this->upgradeLabel($candidate);
        $reference['upgrade_source'] = 'auto';

        return $reference;
    }

    private function findNextSupportedReference(string $family, string $version): ?array
    {
        $family = $this->normalize($family);
        $version = trim($version);
        if ($family === null || $family === '' || $version === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM os_lifecycle_references
            WHERE os_family = :family
        ");
        $stmt->execute([':family' => $family]);

        $best = null;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
            $candidateVersion = trim((string) ($candidate['os_version'] ?? ''));
            if ($candidateVersion === '' || version_compare($candidateVersion, $version, '<=')) {
                continue;
            }

            if ($this->isReferenceObsolete($candidate)) {
                continue;
            }

            if ($best === null || version_compare($candidateVersion, (string) $best['os_version'], '<')) {
                $best = $candidate;
            }
        }

        return $best;
    }

    private function isReferenceObsolete(array $reference): bool
    {
        $supportEndsAt = trim((string) ($reference['support_ends_at'] ?? ''));
        if ($supportEndsAt === '') {
            return false;
        }

        try {
            return new \DateTimeImmutable($supportEndsAt) < new \DateTimeImmutable('today');
        } catch (\Throwable) {
            return false;
        }
    }

    private function upgradeLabel(array $reference): string
    {
        $family = trim((string) ($reference['os_family'] ?? ''));
        $version = trim((string) ($reference['os_version'] ?? ''));
        $codename = trim((string) ($reference['os_codename'] ?? ''));

        $label = trim($family . ' ' . $version);
        if ($codename !== '') {
            $label .= ' (' . $codename . ')';
        }

        return $label;
    }

    private function referenceMatchesCheck(array $reference, array $check): bool
    {
        $referenceFamily = $this->normalize($reference['os_family'] ?? null);
        $checkFamily = $this->normalize($check['os_family'] ?? null);
        if ($referenceFamily === null || $checkFamily === null || $referenceFamily !== $checkFamily) {
            return false;
        }

        $referenceVersion = trim((string) ($reference['os_version'] ?? ''));
        $checkVersion = trim((string) ($check['os_version'] ?? ''));
        if ($referenceVersion !== '' && in_array($referenceVersion, $this->candidateVersions($checkVersion), true)) {
            return true;
        }

        $referenceCodename = $this->normalize($reference['os_codename'] ?? null);
        $checkCodename = $this->normalize($check['os_codename'] ?? null);

        return $referenceCodename !== null && $checkCodename !== null && $referenceCodename === $checkCodename;
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
