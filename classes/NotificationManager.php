<?php
namespace MSM;

use RuntimeException;
use Throwable;

final class NotificationManager
{
    public function __construct(
        private readonly NotificationRepository $repository,
        private readonly SettingsManager $settings
    ) {
    }

    public function dispatchPending(): array
    {
        $queued = $this->repository->queuePendingDeliveries();
        $sent = 0;
        $failed = 0;
        $maxAttempts = max(1, (int) ($this->settings->get('notifications', 'max_attempts') ?? 3));

        foreach ($this->repository->getPendingDeliveries($maxAttempts) as $delivery) {
            if (!$this->repository->claimDelivery((int) $delivery['delivery_id'], $maxAttempts)) {
                continue;
            }

            try {
                $result = $this->sendDelivery($delivery);
                $this->repository->markSent((int) $delivery['delivery_id'], $result['status_code']);
                $sent++;
            } catch (Throwable $exception) {
                $statusCode = $exception instanceof NotificationTransportException
                    ? $exception->statusCode
                    : null;
                $this->repository->markFailed(
                    (int) $delivery['delivery_id'],
                    $statusCode,
                    $exception->getMessage()
                );
                $failed++;
            }
        }

        return ['queued' => $queued, 'sent' => $sent, 'failed' => $failed];
    }

    public function testChannel(array $channel): array
    {
        $delivery = [
            'channel_type' => $channel['channel_type'],
            'endpoint_encrypted' => $channel['endpoint_encrypted'],
            'event_type' => 'test',
            'severity' => 'info',
            'title' => 'Test de notification MSM',
            'alert_message' => 'Le canal de notification fonctionne.',
            'rule_key' => 'notification_test',
            'source' => 'maintenance',
            'server_name' => null,
            'hostname' => null,
            'event_created_at' => date('Y-m-d H:i:s'),
            'alert_id' => 0,
            'alert_status' => 'test',
        ];

        return $this->sendDelivery($delivery);
    }

    private function sendDelivery(array $delivery): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extension PHP curl absente.');
        }

        $endpoint = decrypt((string) $delivery['endpoint_encrypted']);
        if (!$this->isValidEndpoint($endpoint)) {
            throw new RuntimeException('URL du canal invalide.');
        }

        $payload = ($delivery['channel_type'] ?? '') === 'discord'
            ? $this->discordPayload($delivery)
            : $this->webhookPayload($delivery);
        $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encodedPayload === false) {
            throw new RuntimeException('Encodage JSON de la notification impossible.');
        }

        $curl = curl_init($endpoint);
        if ($curl === false) {
            throw new RuntimeException('Initialisation cURL impossible.');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encodedPayload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'MyServerManager-Notifications/1.0',
        ]);
        if (defined('CURLOPT_PROTOCOLS')) {
            curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($response === false) {
            throw new NotificationTransportException(
                $error !== '' ? $error : 'Echec de la requete de notification.',
                $statusCode > 0 ? $statusCode : null
            );
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new NotificationTransportException(
                'Le canal a retourne le code HTTP ' . $statusCode . '.',
                $statusCode
            );
        }

        return ['status_code' => $statusCode];
    }

    private function webhookPayload(array $delivery): array
    {
        return [
            'application' => 'My Server Manager',
            'event' => $delivery['event_type'],
            'severity' => $delivery['severity'],
            'alert' => [
                'id' => (int) ($delivery['alert_id'] ?? 0),
                'rule' => $delivery['rule_key'],
                'source' => $delivery['source'],
                'status' => $delivery['alert_status'],
                'title' => $delivery['title'],
                'message' => $delivery['alert_message'],
            ],
            'target' => [
                'name' => $delivery['server_name'] ?: null,
                'hostname' => $delivery['hostname'] ?: null,
            ],
            'occurred_at' => $delivery['event_created_at'],
            'url' => $this->alertUrl(),
        ];
    }

    private function discordPayload(array $delivery): array
    {
        $eventLabel = match ($delivery['event_type']) {
            'opened' => 'Nouvelle alerte',
            'resolved' => 'Alerte resolue',
            default => 'Test de notification',
        };
        $color = match ($delivery['event_type'] === 'resolved' ? 'resolved' : $delivery['severity']) {
            'critical' => 15158332,
            'warning' => 16753920,
            'resolved' => 3066993,
            default => 3447003,
        };
        $fields = [
            ['name' => 'Severite', 'value' => strtoupper((string) $delivery['severity']), 'inline' => true],
            ['name' => 'Source', 'value' => (string) ($delivery['source'] ?: 'MSM'), 'inline' => true],
        ];
        if (!empty($delivery['server_name']) || !empty($delivery['hostname'])) {
            $fields[] = [
                'name' => 'Cible',
                'value' => (string) ($delivery['server_name'] ?: $delivery['hostname']),
                'inline' => true,
            ];
        }

        $embed = [
            'title' => $eventLabel . ' - ' . $delivery['title'],
            'description' => (string) $delivery['alert_message'],
            'color' => $color,
            'fields' => $fields,
            'timestamp' => date(DATE_ATOM, strtotime((string) $delivery['event_created_at']) ?: time()),
            'footer' => ['text' => 'My Server Manager'],
        ];
        $alertUrl = $this->alertUrl();
        if ($alertUrl !== null) {
            $embed['url'] = $alertUrl;
        }

        return [
            'username' => 'My Server Manager',
            'allowed_mentions' => ['parse' => []],
            'embeds' => [$embed],
        ];
    }

    private function alertUrl(): ?string
    {
        $baseUrl = rtrim(trim((string) ($this->settings->get('notifications', 'public_base_url') ?? '')), '/');

        return $baseUrl !== '' ? $baseUrl . '/pages/alerts.php' : null;
    }

    private function isValidEndpoint(string $endpoint): bool
    {
        if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}

final class NotificationTransportException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $statusCode = null)
    {
        parent::__construct($message);
    }
}
