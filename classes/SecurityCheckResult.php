<?php
namespace MSM;

class SecurityCheckResult
{
    public function __construct(
        public readonly int $serverId,
        public readonly string $status,
        public readonly array $openPorts,
        public readonly ?string $firewallStatus,
        public readonly string $checkedAt,
        public readonly ?int $durationMs = null,
        public readonly ?string $errorMessage = null
    ) {
    }

    public function exposedPortsCount(): int
    {
        return count(array_filter($this->openPorts, fn (array $port): bool => ($port['exposure'] ?? '') === 'public'));
    }
}
