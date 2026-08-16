<?php
namespace MSM;

class ReolinkConnector implements ApiConnectorInterface
{
    /** @var null|callable */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    public function key(): string
    {
        return 'reolink';
    }

    public function label(): string
    {
        return 'Reolink';
    }

    public function testConnection(array $source): array
    {
        $startedAt = microtime(true);
        $client = $this->client($source);

        try {
            $client->login();
            $response = $client->command('GetDevInfo');
            $devInfo = $response[0]['value']['DevInfo'] ?? [];

            return [
                'success' => true,
                'status' => 'success',
                'message' => 'Connexion Reolink et authentification reussies.',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'identity' => [
                    'model' => $devInfo['model'] ?? null,
                    'firmware' => $devInfo['firmVer'] ?? null,
                    'name' => $devInfo['name'] ?? null,
                ],
            ];
        } finally {
            $client->logout();
        }
    }

    public function discover(array $source): array
    {
        $client = $this->client($source);
        $raw = [];

        try {
            $client->login();
            $raw['GetDevInfo'] = $client->command('GetDevInfo');
            $raw['GetChannelstatus'] = $client->optionalCommand('GetChannelstatus');
            $raw['GetHddInfo'] = $client->optionalCommand('GetHddInfo');

            $devInfo = $raw['GetDevInfo'][0]['value']['DevInfo'] ?? [];
            $statuses = $raw['GetChannelstatus'][0]['value']['status'] ?? [];
            $disks = $raw['GetHddInfo'][0]['value']['HddInfo'] ?? [];
            $hubIdentity = (string) ($devInfo['serial'] ?? $devInfo['uid'] ?? $devInfo['name'] ?? $source['hostname']);
            $hubExternalId = 'reolink:hub:' . hash('sha256', $hubIdentity);

            $resources = [$this->hubResource($hubExternalId, $devInfo, count($statuses))];

            foreach ($statuses as $index => $status) {
                if (!is_array($status)) {
                    continue;
                }

                $channel = (int) ($status['channel'] ?? $index);
                $uid = trim((string) ($status['uid'] ?? ''));
                $name = trim((string) ($status['name'] ?? ''));
                if ($uid === '' && $name === '' && (int) ($status['online'] ?? 0) !== 1) {
                    continue;
                }

                $batteryResponse = $client->optionalCommand('GetBatteryInfo', ['channel' => $channel]);
                $raw['GetBatteryInfo:' . $channel] = $batteryResponse;
                $battery = $batteryResponse[0]['value']['Battery'] ?? [];
                $resources[] = $this->cameraResource($hubExternalId, $channel, $uid, $name, $status, is_array($battery) ? $battery : []);
            }

            foreach ($disks as $index => $disk) {
                if (is_array($disk)) {
                    $resources[] = $this->storageResource($hubExternalId, (int) $index, $disk);
                }
            }

            $metricCount = array_sum(array_map(static fn (array $resource): int => count($resource['metrics']), $resources));

            return [
                'status' => 'success',
                'message' => count($resources) . ' ressource(s) et ' . $metricCount . ' metrique(s) detectees.',
                'resources' => $resources,
                'raw' => $raw,
            ];
        } finally {
            $client->logout();
        }
    }

    protected function client(array $source): ReolinkApiClient
    {
        return new ReolinkApiClient($source, $this->transport);
    }

    private function hubResource(string $externalId, array $info, int $cameraCount): array
    {
        return [
            'external_id' => $externalId,
            'resource_type' => 'hub',
            'name' => (string) ($info['name'] ?? $info['model'] ?? 'Hub Reolink'),
            'parent_external_id' => null,
            'metadata' => [
                'manufacturer' => 'Reolink',
                'model' => $info['model'] ?? null,
                'firmware' => $info['firmVer'] ?? null,
                'hardware_version' => $info['hardVer'] ?? null,
                'serial' => $info['serial'] ?? null,
            ],
            'metrics' => [
                $this->metric('api_available', 'API disponible', 'BOOLEAN', null, true),
                $this->metric('model', 'Modele', 'TEXT', null, $info['model'] ?? null, false),
                $this->metric('firmware', 'Firmware', 'TEXT', null, $info['firmVer'] ?? null, false),
                $this->metric('camera_count', 'Nombre de cameras', 'INTEGER', null, $cameraCount),
            ],
        ];
    }

    private function cameraResource(string $parentId, int $channel, string $uid, string $name, array $status, array $battery): array
    {
        $externalId = 'reolink:camera:' . ($uid !== '' ? $uid : hash('sha256', $parentId . ':' . $channel));
        $metrics = [
            $this->metric('online', 'Camera en ligne', 'BOOLEAN', null, (int) ($status['online'] ?? 0) === 1),
            $this->metric('sleeping', 'Camera en veille', 'BOOLEAN', null, (int) ($status['sleep'] ?? 0) === 1),
        ];

        $candidates = [
            ['battery_percent', 'Niveau batterie', 'PERCENTAGE', '%', ['batteryPercent', 'battery', 'percent']],
            ['temperature', 'Temperature batterie', 'TEMPERATURE', 'degC', ['temperature', 'temp']],
            ['voltage', 'Tension batterie brute', 'VOLTAGE', null, ['voltage']],
            ['current', 'Courant batterie brut', 'CURRENT', null, ['current']],
            ['charge_status', 'Etat de charge brut', 'ENUM', null, ['chargeStatus', 'charge']],
            ['adapter_status', 'Etat adaptateur brut', 'ENUM', null, ['adapterStatus', 'adapter']],
        ];
        foreach ($candidates as [$key, $label, $type, $unit, $keys]) {
            $value = $this->firstValue($battery, $keys);
            if ($value !== null) {
                $semanticsUnknown = in_array($type, ['ENUM', 'VOLTAGE', 'CURRENT'], true);
                $metrics[] = $this->metric($key, $label, $type, $unit, $value, !$semanticsUnknown, [
                    'raw_semantics' => $semanticsUnknown,
                    'manufacturer_unit_unknown' => in_array($type, ['VOLTAGE', 'CURRENT'], true),
                ]);
            }
        }

        return [
            'external_id' => $externalId,
            'resource_type' => 'camera',
            'name' => $name !== '' ? $name : 'Camera canal ' . $channel,
            'parent_external_id' => $parentId,
            'metadata' => ['channel' => $channel, 'uid' => $uid !== '' ? $uid : null],
            'metrics' => $metrics,
        ];
    }

    private function storageResource(string $parentId, int $index, array $disk): array
    {
        $capacity = is_numeric($disk['capacity'] ?? null) ? (float) $disk['capacity'] : null;
        $free = is_numeric($disk['size'] ?? null) ? (float) $disk['size'] : null;
        $usage = $capacity !== null && $capacity > 0 && $free !== null
            ? round(100 * (1 - ($free / $capacity)), 2)
            : null;

        $metrics = [
            $this->metric('available', 'Stockage disponible', 'BOOLEAN', null, (int) ($disk['format'] ?? 0) === 1 && (int) ($disk['mount'] ?? 0) === 1),
        ];
        if ($capacity !== null) {
            $metrics[] = $this->metric('capacity_raw', 'Capacite brute', 'BYTES', null, $capacity, false, ['manufacturer_unit_unknown' => true]);
        }
        if ($free !== null) {
            $metrics[] = $this->metric('free_raw', 'Espace libre brut', 'BYTES', null, $free, false, ['manufacturer_unit_unknown' => true]);
        }
        if ($usage !== null) {
            $metrics[] = $this->metric('usage_percent', 'Occupation stockage', 'PERCENTAGE', '%', $usage);
        }

        return [
            'external_id' => 'reolink:storage:' . hash('sha256', $parentId . ':' . $index),
            'resource_type' => 'storage',
            'name' => 'Stockage ' . ($index + 1),
            'parent_external_id' => $parentId,
            'metadata' => ['index' => $index, 'storage_type' => $disk['storageType'] ?? null],
            'metrics' => $metrics,
        ];
    }

    private function metric(string $key, string $name, string $type, ?string $unit, mixed $value, bool $enabled = true, array $metadata = []): array
    {
        return [
            'external_key' => $key,
            'name' => $name,
            'data_type' => $type,
            'unit' => $unit,
            'value' => $value,
            'raw_value' => $value,
            'enabled' => $enabled,
            'collection_interval_minutes' => 15,
            'metadata' => $metadata,
        ];
    }

    private function firstValue(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }
        return null;
    }
}
