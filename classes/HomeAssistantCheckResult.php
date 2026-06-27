<?php
namespace MSM;

class HomeAssistantCheckResult
{
    public function __construct(
        public readonly int $serverId,
        public readonly ?string $collector,
        public readonly string $status,
        public readonly ?string $installationType,
        public readonly ?string $haVersion,
        public readonly ?string $haLatestVersion,
        public readonly ?bool $haUpdateAvailable,
        public readonly ?string $supervisorVersion,
        public readonly ?string $supervisorLatestVersion,
        public readonly ?bool $supervisorUpdateAvailable,
        public readonly ?string $osVersion,
        public readonly ?string $osLatestVersion,
        public readonly ?bool $osUpdateAvailable,
        public readonly ?string $hostOs,
        public readonly ?string $kernel,
        public readonly string $checkedAt,
        public readonly ?int $durationMs = null,
        public readonly ?string $errorMessage = null,
        public readonly ?array $rawSummary = null
    ) {
    }

    public function hasUpdate(): bool
    {
        return $this->haUpdateAvailable === true
            || $this->supervisorUpdateAvailable === true
            || $this->osUpdateAvailable === true;
    }
}
