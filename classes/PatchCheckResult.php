<?php
namespace MSM;

class PatchCheckResult
{
    public function __construct(
        public readonly int $serverId,
        public readonly ?string $collector,
        public readonly string $status,
        public readonly int $normalUpdatesCount,
        public readonly int $securityUpdatesCount,
        public readonly bool $rebootRequired,
        public readonly string $checkedAt,
        public readonly ?int $durationMs = null,
        public readonly ?string $errorMessage = null,
        public readonly array $updates = []
    ) {
    }

    public function hasUpdates(): bool
    {
        return ($this->normalUpdatesCount + $this->securityUpdatesCount) > 0;
    }
}
