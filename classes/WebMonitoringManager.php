<?php
namespace MSM;

use PDO;

final class WebMonitoringManager
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?HttpCheckCollector $collector = null
    ) {
    }

    public function run(bool $force = false): array
    {
        $repository = new WebTargetRepository($this->pdo);
        $collector = $this->collector ?? new HttpCheckCollector();
        $targets = $repository->dueTargets($force);
        $results = [];

        foreach ($targets as $target) {
            try {
                $result = $collector->check($target);
            } catch (\Throwable $e) {
                $result = [
                    'success' => false,
                    'http_status' => null,
                    'error_type' => 'collector',
                    'error_message' => mb_substr($e->getMessage(), 0, 500),
                    'dns_ms' => null,
                    'connect_ms' => null,
                    'tls_ms' => null,
                    'ttfb_ms' => null,
                    'total_ms' => null,
                    'final_url' => (string) $target['url'],
                    'redirect_count' => 0,
                    'tls_valid' => null,
                    'certificate_expires_at' => null,
                    'certificate_expiry_days' => null,
                    'content_matched' => null,
                ];
            }

            $repository->saveResult((int) $target['id'], $result, (int) $target['interval_minutes']);
            $results[] = ['target' => $target, 'result' => $result];
        }

        return $results;
    }
}
