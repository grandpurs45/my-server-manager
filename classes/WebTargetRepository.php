<?php
namespace MSM;

use InvalidArgumentException;
use PDO;

final class WebTargetRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listTargets(): array
    {
        $stmt = $this->pdo->query(
            'SELECT wt.*,
                    wr.success AS last_success,
                    wr.http_status AS last_http_status,
                    wr.error_type AS last_error_type,
                    wr.error_message AS last_error_message,
                    wr.total_ms AS last_total_ms,
                    wr.certificate_expiry_days AS last_certificate_expiry_days,
                    wr.content_matched AS last_content_matched,
                    wr.checked_at AS result_checked_at
             FROM web_targets wt
             LEFT JOIN web_check_results wr
               ON wr.id = (
                   SELECT wr2.id
                   FROM web_check_results wr2
                   WHERE wr2.web_target_id = wt.id
                   ORDER BY wr2.checked_at DESC, wr2.id DESC
                   LIMIT 1
               )
             ORDER BY wt.name ASC, wt.id ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM web_targets WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        return $target ?: null;
    }

    public function create(array $data): int
    {
        $target = $this->normalize($data);
        $stmt = $this->pdo->prepare(
            'INSERT INTO web_targets (
                name, url, enabled, environment, criticality, interval_minutes,
                timeout_seconds, follow_redirects, verify_tls, expected_status_codes,
                expected_content, failure_threshold, next_check_at
             ) VALUES (
                :name, :url, :enabled, :environment, :criticality, :interval_minutes,
                :timeout_seconds, :follow_redirects, :verify_tls, :expected_status_codes,
                :expected_content, :failure_threshold, NOW()
             )'
        );
        $stmt->execute($target);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        if ($this->find($id) === null) {
            throw new InvalidArgumentException('Cible URL introuvable.');
        }

        $target = $this->normalize($data);
        $target[':id'] = $id;
        $stmt = $this->pdo->prepare(
            'UPDATE web_targets
             SET name = :name,
                 url = :url,
                 enabled = :enabled,
                 environment = :environment,
                 criticality = :criticality,
                 interval_minutes = :interval_minutes,
                 timeout_seconds = :timeout_seconds,
                 follow_redirects = :follow_redirects,
                 verify_tls = :verify_tls,
                 expected_status_codes = :expected_status_codes,
                 expected_content = :expected_content,
                 failure_threshold = :failure_threshold,
                 next_check_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute($target);
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE web_targets
             SET enabled = :enabled,
                 next_check_at = CASE WHEN :enabled_due = 1 THEN NOW() ELSE next_check_at END
             WHERE id = :id'
        );
        $stmt->execute([
            ':enabled' => $enabled ? 1 : 0,
            ':enabled_due' => $enabled ? 1 : 0,
            ':id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM web_targets WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function dueTargets(bool $force = false): array
    {
        $sql = 'SELECT * FROM web_targets WHERE enabled = 1';
        if (!$force) {
            $sql .= ' AND (next_check_at IS NULL OR next_check_at <= NOW())';
        }
        $sql .= ' ORDER BY COALESCE(next_check_at, created_at) ASC, id ASC';

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveResult(int $targetId, array $result, int $intervalMinutes): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO web_check_results (
                    web_target_id, success, http_status, error_type, error_message,
                    dns_ms, connect_ms, tls_ms, ttfb_ms, total_ms, final_url,
                    redirect_count, tls_valid, certificate_expires_at,
                    certificate_expiry_days, content_matched, checked_at
                 ) VALUES (
                    :web_target_id, :success, :http_status, :error_type, :error_message,
                    :dns_ms, :connect_ms, :tls_ms, :ttfb_ms, :total_ms, :final_url,
                    :redirect_count, :tls_valid, :certificate_expires_at,
                    :certificate_expiry_days, :content_matched, NOW()
                 )'
            );
            $stmt->execute([
                ':web_target_id' => $targetId,
                ':success' => !empty($result['success']) ? 1 : 0,
                ':http_status' => $result['http_status'],
                ':error_type' => $result['error_type'],
                ':error_message' => $result['error_message'],
                ':dns_ms' => $result['dns_ms'],
                ':connect_ms' => $result['connect_ms'],
                ':tls_ms' => $result['tls_ms'],
                ':ttfb_ms' => $result['ttfb_ms'],
                ':total_ms' => $result['total_ms'],
                ':final_url' => $result['final_url'],
                ':redirect_count' => $result['redirect_count'],
                ':tls_valid' => $result['tls_valid'],
                ':certificate_expires_at' => $result['certificate_expires_at'],
                ':certificate_expiry_days' => $result['certificate_expiry_days'],
                ':content_matched' => $result['content_matched'],
            ]);

            $update = $this->pdo->prepare(
                'UPDATE web_targets
                 SET consecutive_failures = CASE WHEN :success = 1 THEN 0 ELSE consecutive_failures + 1 END,
                     last_checked_at = NOW(),
                     next_check_at = DATE_ADD(NOW(), INTERVAL :interval_minutes MINUTE)
                 WHERE id = :id'
            );
            $update->execute([
                ':success' => !empty($result['success']) ? 1 : 0,
                ':interval_minutes' => max(1, $intervalMinutes),
                ':id' => $targetId,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function normalize(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $url = trim((string) ($data['url'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) {
            throw new InvalidArgumentException('Le nom est obligatoire et limite a 150 caracteres.');
        }
        if (mb_strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('URL invalide.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Seuls les protocoles HTTP et HTTPS sont autorises.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Les identifiants ne doivent pas etre places dans l URL.');
        }

        $statusCodes = trim((string) ($data['expected_status_codes'] ?? '200-399'));
        if (!$this->validStatusCodeExpression($statusCodes)) {
            throw new InvalidArgumentException('Codes HTTP attendus invalides. Exemple : 200-399,401.');
        }

        $criticality = strtolower(trim((string) ($data['criticality'] ?? 'medium')));
        if (!preg_match('/^[a-z0-9_-]{1,20}$/', $criticality)) {
            $criticality = 'medium';
        }

        $environment = strtolower(trim((string) ($data['environment'] ?? 'production')));
        if (!preg_match('/^[a-z0-9_-]{1,50}$/', $environment)) {
            $environment = 'production';
        }

        $expectedContent = trim((string) ($data['expected_content'] ?? ''));

        return [
            ':name' => $name,
            ':url' => $url,
            ':enabled' => !empty($data['enabled']) ? 1 : 0,
            ':environment' => $environment,
            ':criticality' => $criticality,
            ':interval_minutes' => min(1440, max(1, (int) ($data['interval_minutes'] ?? 5))),
            ':timeout_seconds' => min(60, max(1, (int) ($data['timeout_seconds'] ?? 10))),
            ':follow_redirects' => !empty($data['follow_redirects']) ? 1 : 0,
            ':verify_tls' => !empty($data['verify_tls']) ? 1 : 0,
            ':expected_status_codes' => $statusCodes,
            ':expected_content' => $expectedContent !== '' ? mb_substr($expectedContent, 0, 255) : null,
            ':failure_threshold' => min(10, max(1, (int) ($data['failure_threshold'] ?? 2))),
        ];
    }

    private function validStatusCodeExpression(string $expression): bool
    {
        if ($expression === '' || mb_strlen($expression) > 100) {
            return false;
        }

        foreach (preg_split('/\s*,\s*/', $expression) ?: [] as $part) {
            if (!preg_match('/^([1-5][0-9]{2})(?:-([1-5][0-9]{2}))?$/', $part, $matches)) {
                return false;
            }
            if (isset($matches[2]) && (int) $matches[2] < (int) $matches[1]) {
                return false;
            }
        }

        return true;
    }
}
