<?php
namespace MSM;

class AlertCandidate
{
    public function __construct(
        public readonly string $ruleKey,
        public readonly ?int $serverId,
        public readonly string $severity,
        public readonly string $title,
        public readonly string $message,
        public readonly string $fingerprint
    ) {
    }
}
