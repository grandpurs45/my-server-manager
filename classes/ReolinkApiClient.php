<?php
namespace MSM;

use JsonException;

final class ReolinkApiClient
{
    private ?string $token = null;

    /** @var null|callable */
    private $transport;

    public function __construct(private readonly array $source, ?callable $transport = null)
    {
        $this->transport = $transport;
    }

    public function login(): void
    {
        $response = $this->request('Login', [[
            'cmd' => 'Login',
            'action' => 0,
            'param' => ['User' => [
                'userName' => (string) ($this->source['username'] ?? ''),
                'password' => (string) ($this->source['secret'] ?? ''),
            ]],
        ]], false);

        $token = $response[0]['value']['Token']['name'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new ApiConnectorException('Authentification Reolink refusee ou reponse de connexion invalide.', 'authentication_failed');
        }

        $this->token = $token;
    }

    public function command(string $command, array $param = []): array
    {
        if ($this->token === null) {
            throw new ApiConnectorException('Session Reolink absente.', 'authentication_required');
        }

        $request = ['cmd' => $command];
        if ($command !== 'GetChannelstatus') {
            $request['action'] = 0;
            $request['param'] = $param;
        }

        return $this->request($command, [$request]);
    }

    public function optionalCommand(string $command, array $param = []): array
    {
        try {
            return $this->command($command, $param);
        } catch (ApiConnectorException $exception) {
            if (in_array($exception->errorType, ['command_unsupported', 'api_error'], true)) {
                return [];
            }
            throw $exception;
        }
    }

    public function logout(): void
    {
        if ($this->token === null) {
            return;
        }

        try {
            $this->request('Logout', [[
                'cmd' => 'Logout',
                'action' => 0,
                'param' => [],
            ]]);
        } catch (\Throwable) {
            // La deconnexion ne doit jamais masquer le resultat du controle.
        } finally {
            $this->token = null;
        }
    }

    private function request(string $command, array $body, bool $withToken = true): array
    {
        $query = ['cmd' => $command];
        if ($withToken && $this->token !== null) {
            $query['token'] = $this->token;
        }

        $url = sprintf(
            '%s://%s:%d/cgi-bin/api.cgi?%s',
            $this->source['protocol'],
            $this->source['hostname'],
            (int) $this->source['port'],
            http_build_query($query)
        );

        try {
            $payload = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new ApiConnectorException('Impossible de preparer la requete Reolink.', 'invalid_request', $exception);
        }

        $raw = $this->transport !== null
            ? ($this->transport)($url, $payload, $this->source)
            : $this->curlPost($url, $payload);

        try {
            $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ApiConnectorException('La source Reolink a retourne une reponse non JSON.', 'invalid_response', $exception);
        }

        if (!is_array($decoded) || !isset($decoded[0]) || !is_array($decoded[0])) {
            throw new ApiConnectorException('Structure de reponse Reolink inattendue.', 'invalid_response');
        }

        $first = $decoded[0];
        if ((int) ($first['code'] ?? 1) !== 0) {
            $rspCode = (int) ($first['error']['rspCode'] ?? $first['value']['rspCode'] ?? 0);
            $detail = trim((string) ($first['error']['detail'] ?? $first['value']['detail'] ?? 'commande refusee'));
            $type = $command === 'Login' ? 'authentication_failed' : ($rspCode === -6 ? 'command_unsupported' : 'api_error');
            throw new ApiConnectorException('Reolink ' . $command . ' : ' . $detail . ' (' . $rspCode . ').', $type);
        }

        return $decoded;
    }

    private function curlPost(string $url, string $payload): string
    {
        if (!function_exists('curl_init')) {
            throw new ApiConnectorException('Extension PHP cURL absente.', 'dependency_missing');
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => (int) $this->source['timeout_seconds'],
            CURLOPT_TIMEOUT => (int) $this->source['timeout_seconds'],
            CURLOPT_SSL_VERIFYPEER => (bool) $this->source['verify_tls'],
            CURLOPT_SSL_VERIFYHOST => (bool) $this->source['verify_tls'] ? 2 : 0,
        ]);

        $raw = curl_exec($handle);
        if ($raw === false) {
            $code = curl_errno($handle);
            $message = curl_error($handle);
            curl_close($handle);
            $type = match ($code) {
                CURLE_COULDNT_RESOLVE_HOST => 'dns_error',
                CURLE_COULDNT_CONNECT => 'connection_refused',
                CURLE_OPERATION_TIMEDOUT => 'timeout',
                CURLE_SSL_CONNECT_ERROR, CURLE_PEER_FAILED_VERIFICATION => 'tls_error',
                default => 'network_error',
            };
            throw new ApiConnectorException('Connexion Reolink impossible : ' . $message, $type);
        }

        $httpCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new ApiConnectorException('La source Reolink repond en HTTP ' . $httpCode . '.', 'http_error');
        }

        return (string) $raw;
    }
}
