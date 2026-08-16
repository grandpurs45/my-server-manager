<?php
namespace MSM;

use PDO;

final class ApiIntegrationManager
{
    private ApiSourceRepository $repository;
    private ApiConnectorRegistry $registry;
    private SettingsManager $settings;

    public function __construct(PDO $pdo, ?ApiConnectorRegistry $registry = null)
    {
        $this->repository = new ApiSourceRepository($pdo);
        $this->registry = $registry ?? new ApiConnectorRegistry();
        $this->settings = new SettingsManager($pdo);
    }

    public function test(int $sourceId): array
    {
        $source = $this->requireSource($sourceId);
        try {
            $result = $this->registry->get($source['connector_type'])->testConnection($source);
        } catch (ApiConnectorException $exception) {
            $result = ['success' => false, 'status' => $exception->errorType, 'message' => $exception->getMessage()];
        }
        $this->repository->saveTestResult($sourceId, $result);
        return $result;
    }

    public function discover(int $sourceId): array
    {
        $source = $this->requireSource($sourceId);
        $runId = $this->repository->beginDiscovery($sourceId);
        try {
            $result = $this->registry->get($source['connector_type'])->discover($source);
            $retention = max(1, (int) ($this->settings->get('api_integrations', 'raw_retention_days') ?? 7));
            $this->repository->persistDiscovery($sourceId, $runId, $result, $retention);
            return $result;
        } catch (\Throwable $exception) {
            $this->repository->failDiscovery($runId, $exception);
            throw $exception;
        }
    }

    public function collectDue(): array
    {
        $summary = ['sources' => 0, 'metrics' => 0, 'errors' => 0];
        foreach ($this->repository->dueSources() as $dueSource) {
            $sourceId = (int) $dueSource['id'];
            $runId = null;
            try {
                $source = $this->requireSource($sourceId);
                $result = $this->registry->get($source['connector_type'])->discover($source);
                if ((int) $dueSource['discovery_due'] === 1) {
                    $runId = $this->repository->beginDiscovery($sourceId);
                    $retention = max(1, (int) ($this->settings->get('api_integrations', 'raw_retention_days') ?? 7));
                    $this->repository->persistDiscovery($sourceId, $runId, $result, $retention, false);
                    $runId = null;
                }
                $summary['metrics'] += $this->repository->storeCollection($sourceId, $result);
                $summary['sources']++;
            } catch (\Throwable $exception) {
                if ($runId !== null) {
                    $this->repository->failDiscovery($runId, $exception);
                }
                $summary['errors']++;
                fwrite(STDERR, '[source ' . $sourceId . '] ' . $exception->getMessage() . PHP_EOL);
            }
        }
        return $summary;
    }

    private function requireSource(int $sourceId): array
    {
        $source = $this->repository->find($sourceId, true);
        if ($source === null) {
            throw new \InvalidArgumentException('Source API introuvable.');
        }
        return $source;
    }
}
