<?php
namespace MSM;

class HardwareHealthCheckResult
{
    public function __construct(
        public readonly int $serverId,
        public readonly ?string $collector,
        public readonly string $status,
        public readonly array $temperatures,
        public readonly array $smartDisks,
        public readonly string $checkedAt,
        public readonly ?int $durationMs = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $smartErrorMessage = null
    ) {
    }

    public function maxTemperature(): ?float
    {
        if ($this->temperatures === []) {
            return null;
        }

        return max(array_map(
            fn (array $reading): float => (float) ($reading['temperature'] ?? 0),
            $this->temperatures
        ));
    }
}
