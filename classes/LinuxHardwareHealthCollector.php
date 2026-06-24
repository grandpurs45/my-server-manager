<?php
namespace MSM;

require_once __DIR__ . '/../includes/crypto.php';

use phpseclib3\Net\SSH2;

class LinuxHardwareHealthCollector
{
    public function collect(array $server): HardwareHealthCheckResult
    {
        $startedAt = microtime(true);
        $checkedAt = date('Y-m-d H:i:s');
        $serverId = (int) $server['id'];

        try {
            if (empty($server['ssh_enabled']) || empty($server['ssh_user']) || empty($server['ssh_password'])) {
                return $this->result($serverId, null, 'error', [], [], $checkedAt, $startedAt, 'SSH non configure ou desactive.');
            }

            if (!in_array(($server['target_type'] ?? 'other'), ['linux', 'proxmox'], true)) {
                return $this->result($serverId, null, 'unsupported', [], [], $checkedAt, $startedAt, 'Type de cible non supporte par le collecteur materiel Linux.');
            }

            $ssh = new SSH2($server['hostname'], (int) ($server['ssh_port'] ?? 22));
            $ssh->setTimeout(20);

            if (!$ssh->login($server['ssh_user'], decrypt($server['ssh_password']))) {
                return $this->result($serverId, null, 'error', [], [], $checkedAt, $startedAt, 'Connexion SSH echouee.');
            }

            $readings = [];
            $temperatureCollector = null;
            if (trim($ssh->exec('command -v sensors 2>/dev/null')) !== '') {
                $readings = $this->parseSensorsJson($ssh->exec('LC_ALL=C sensors -j 2>/dev/null'));
                if ($readings !== []) {
                    $temperatureCollector = 'lm_sensors';
                }
            }

            if ($readings !== []) {
                // lm-sensors a deja fourni les sondes les plus detaillees.
            } else {
                $readings = $this->parseThermalZones($ssh->exec(
                    'for zone in /sys/class/thermal/thermal_zone*; do '
                    . '[ -r "$zone/temp" ] || continue; '
                    . 'type=$(cat "$zone/type" 2>/dev/null); temp=$(cat "$zone/temp" 2>/dev/null); '
                    . 'printf "%s|%s|%s\n" "$(basename "$zone")" "$type" "$temp"; '
                    . 'done'
                ));
                if ($readings !== []) {
                    $temperatureCollector = 'sysfs_thermal';
                }
            }

            $smartDisks = [];
            $smartError = null;
            if (($server['hardware_profile'] ?? 'unknown') === 'physical') {
                [$smartDisks, $smartError] = $this->collectSmartDisks($ssh);
            }

            $collectors = array_values(array_filter([
                $temperatureCollector,
                $smartDisks !== [] ? 'smartctl' : null,
            ]));

            if ($readings !== [] || $smartDisks !== []) {
                return $this->result(
                    $serverId,
                    implode('+', $collectors) ?: null,
                    'ok',
                    $readings,
                    $smartDisks,
                    $checkedAt,
                    $startedAt,
                    null,
                    $smartError
                );
            }

            $message = 'Aucune sonde de temperature lisible. Installer lm-sensors ou verifier /sys/class/thermal.';
            if (($server['hardware_profile'] ?? 'unknown') === 'physical' && $smartError !== null) {
                $message .= ' SMART: ' . $smartError;
            }

            return $this->result(
                $serverId,
                null,
                'unsupported',
                [],
                [],
                $checkedAt,
                $startedAt,
                $message,
                $smartError
            );
        } catch (\Throwable $e) {
            return $this->result($serverId, null, 'error', [], [], $checkedAt, $startedAt, $e->getMessage());
        }
    }

    private function collectSmartDisks(SSH2 $ssh): array
    {
        $directAvailable = trim($ssh->exec('command -v smartctl 2>/dev/null')) !== '';
        $sudoAvailable = trim($ssh->exec('sudo -n smartctl --version 2>/dev/null')) !== '';

        if (!$directAvailable && !$sudoAvailable) {
            return [[], 'smartctl non installe ou inaccessible.'];
        }

        $prefixes = $directAvailable ? ['', 'sudo -n '] : ['sudo -n '];
        $lastError = null;

        foreach ($prefixes as $prefix) {
            $scanOutput = $ssh->exec($prefix . 'smartctl --scan-open -j 2>&1');
            $scan = json_decode($scanOutput, true);
            $devices = is_array($scan) && isset($scan['devices']) && is_array($scan['devices'])
                ? $scan['devices']
                : [];

            if ($devices === []) {
                $lastError = $this->smartctlError($scan, $scanOutput, 'Aucun disque SMART detecte.');
                continue;
            }

            $disks = [];
            foreach ($devices as $device) {
                $deviceName = (string) ($device['name'] ?? '');
                $deviceType = (string) ($device['type'] ?? '');
                if (!preg_match('#^/dev/[a-zA-Z0-9._/-]+$#', $deviceName)) {
                    continue;
                }
                if ($deviceType !== '' && !preg_match('/^[a-zA-Z0-9,+_-]+$/', $deviceType)) {
                    $deviceType = '';
                }

                $command = $prefix . 'smartctl -a -j ';
                if ($deviceType !== '') {
                    $command .= '-d ' . $deviceType . ' ';
                }
                $command .= $deviceName . ' 2>&1';

                $output = $ssh->exec($command);
                $data = json_decode($output, true);
                if (!is_array($data)) {
                    $disks[] = $this->smartDiskError($deviceName, $deviceType, 'Sortie smartctl JSON invalide.');
                    continue;
                }

                $disks[] = $this->parseSmartDisk($deviceName, $deviceType, $data, $output);
            }

            if ($disks !== []) {
                return [$disks, null];
            }
        }

        return [[], $lastError ?? 'Lecture SMART impossible.'];
    }

    private function parseSmartDisk(string $deviceName, string $deviceType, array $data, string $rawOutput): array
    {
        $temperature = $this->numericValue($data['temperature']['current'] ?? null);
        $powerOnHours = $this->integerValue($data['power_on_time']['hours'] ?? null);

        foreach (($data['ata_smart_attributes']['table'] ?? []) as $attribute) {
            $attributeName = strtolower((string) ($attribute['name'] ?? ''));
            if ($powerOnHours === null && str_contains($attributeName, 'power_on_hours')) {
                $powerOnHours = $this->integerValue($attribute['raw']['value'] ?? null);
            }
            if ($temperature === null && str_contains($attributeName, 'temperature')) {
                $temperature = $this->numericValue($attribute['raw']['value'] ?? null);
            }
        }

        $percentageUsed = $this->numericValue(
            $data['nvme_smart_health_information_log']['percentage_used']
                ?? $data['scsi_percentage_used_endurance_indicator']
                ?? null
        );
        $mediaErrors = $this->integerValue(
            $data['nvme_smart_health_information_log']['media_errors']
                ?? $data['ata_smart_error_log']['summary']['count']
                ?? null
        );
        $smartMessages = $data['smartctl']['messages'] ?? [];
        $errorMessage = null;
        if (is_array($smartMessages)) {
            $messages = array_values(array_filter(array_map(
                fn (array $message): string => trim((string) ($message['string'] ?? '')),
                array_filter($smartMessages, 'is_array')
            )));
            $errorMessage = $messages !== [] ? implode(' ', $messages) : null;
        }
        if ($errorMessage === null && json_decode($rawOutput, true) === null) {
            $errorMessage = 'Sortie smartctl illisible.';
        }

        return [
            'device_name' => $deviceName,
            'device_type' => $deviceType !== '' ? $deviceType : null,
            'protocol' => $data['device']['protocol'] ?? null,
            'model_name' => $data['model_name'] ?? $data['model_family'] ?? $data['scsi_model_name'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'capacity_bytes' => $this->integerValue($data['user_capacity']['bytes'] ?? null),
            'smart_supported' => $this->boolValue($data['smart_support']['available'] ?? null),
            'smart_enabled' => $this->boolValue($data['smart_support']['enabled'] ?? null),
            'smart_passed' => $this->boolValue($data['smart_status']['passed'] ?? null),
            'temperature_celsius' => $temperature,
            'power_on_hours' => $powerOnHours,
            'percentage_used' => $percentageUsed,
            'media_errors' => $mediaErrors,
            'error_message' => $errorMessage,
        ];
    }

    private function smartDiskError(string $deviceName, string $deviceType, string $message): array
    {
        return [
            'device_name' => $deviceName,
            'device_type' => $deviceType !== '' ? $deviceType : null,
            'error_message' => $message,
        ];
    }

    private function smartctlError(mixed $data, string $rawOutput, string $fallback): string
    {
        if (is_array($data)) {
            foreach (($data['smartctl']['messages'] ?? []) as $message) {
                $text = trim((string) ($message['string'] ?? ''));
                if ($text !== '') {
                    return $text;
                }
            }
        }

        $rawOutput = trim($rawOutput);
        return $rawOutput !== '' ? mb_substr($rawOutput, 0, 240) : $fallback;
    }

    private function boolValue(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    private function numericValue(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function integerValue(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    private function parseSensorsJson(string $output): array
    {
        $data = json_decode($output, true);
        if (!is_array($data)) {
            return [];
        }

        $readings = [];
        foreach ($data as $chip => $groups) {
            if (!is_array($groups)) {
                continue;
            }

            foreach ($groups as $group => $values) {
                if (!is_array($values)) {
                    continue;
                }

                foreach ($values as $key => $value) {
                    if (!str_ends_with((string) $key, '_input') || !is_numeric($value)) {
                        continue;
                    }

                    $temperature = (float) $value;
                    if ($temperature < -50 || $temperature > 200) {
                        continue;
                    }

                    $prefix = substr((string) $key, 0, -6);
                    $label = trim((string) ($values[$prefix . '_label'] ?? $group));
                    $readings[] = [
                        'key' => $chip . '/' . $group . '/' . $key,
                        'label' => $label !== '' ? $label : (string) $group,
                        'type' => $this->detectSensorType((string) $chip . ' ' . (string) $group . ' ' . $label),
                        'temperature' => $temperature,
                    ];
                }
            }
        }

        return $readings;
    }

    private function parseThermalZones(string $output): array
    {
        $readings = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $parts = explode('|', trim($line), 3);
            if (count($parts) !== 3 || !is_numeric($parts[2])) {
                continue;
            }

            $temperature = (float) $parts[2];
            if (abs($temperature) > 200) {
                $temperature /= 1000;
            }
            if ($temperature < -50 || $temperature > 200) {
                continue;
            }

            $label = trim($parts[1]) !== '' ? trim($parts[1]) : $parts[0];
            $readings[] = [
                'key' => $parts[0],
                'label' => $label,
                'type' => $this->detectSensorType($label),
                'temperature' => $temperature,
            ];
        }

        return $readings;
    }

    private function detectSensorType(string $label): string
    {
        $normalized = strtolower($label);

        if (preg_match('/nvme|drive|disk|ssd|hdd|composite/', $normalized)) {
            return 'disk';
        }
        if (preg_match('/cpu|core|package|tctl|tdie|soc/', $normalized)) {
            return 'cpu';
        }
        if (preg_match('/board|motherboard|pch|acpi|system/', $normalized)) {
            return 'motherboard';
        }

        return 'other';
    }

    private function result(
        int $serverId,
        ?string $collector,
        string $status,
        array $temperatures,
        array $smartDisks,
        string $checkedAt,
        float $startedAt,
        ?string $errorMessage = null,
        ?string $smartErrorMessage = null
    ): HardwareHealthCheckResult {
        return new HardwareHealthCheckResult(
            serverId: $serverId,
            collector: $collector,
            status: $status,
            temperatures: $temperatures,
            smartDisks: $smartDisks,
            checkedAt: $checkedAt,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            errorMessage: $errorMessage,
            smartErrorMessage: $smartErrorMessage
        );
    }
}
