<?php
namespace MSM;

final class ProxmoxOsDetector
{
    public const FAMILY = 'proxmox_ve';

    public static function command(): string
    {
        return 'PATH=/usr/sbin:/usr/bin:/sbin:/bin; '
            . 'if command -v pveversion >/dev/null 2>&1; then pveversion 2>/dev/null; '
            . 'elif [ -x /usr/bin/pveversion ]; then /usr/bin/pveversion 2>/dev/null; fi';
    }

    public static function parse(string $output): ?array
    {
        if (preg_match('/(?:^|\s)pve-manager\/([0-9]+(?:\.[0-9]+){0,2})/i', trim($output), $matches) !== 1) {
            return null;
        }

        $fullVersion = $matches[1];
        $majorVersion = explode('.', $fullVersion, 2)[0];

        return [
            'family' => self::FAMILY,
            'version' => $majorVersion,
            'full_version' => $fullVersion,
            'pretty_name' => 'Proxmox VE ' . $fullVersion,
        ];
    }
}
