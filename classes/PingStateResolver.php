<?php
namespace MSM;

final class PingStateResolver
{
    public function resolve(
        string $previousStatus,
        int $previousFailures,
        string $rawStatus,
        int $failureThreshold
    ): array {
        $failureThreshold = max(1, $failureThreshold);

        if ($rawStatus === 'up') {
            return [
                'status' => 'up',
                'consecutive_failures' => 0,
                'pending_failure' => false,
            ];
        }

        $consecutiveFailures = max(0, $previousFailures) + 1;
        $confirmed = $consecutiveFailures >= $failureThreshold;

        return [
            'status' => $confirmed ? 'down' : ($previousStatus === 'up' ? 'up' : 'down'),
            'consecutive_failures' => $consecutiveFailures,
            'pending_failure' => !$confirmed,
        ];
    }
}
