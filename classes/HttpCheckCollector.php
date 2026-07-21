<?php
namespace MSM;

use RuntimeException;

final class HttpCheckCollector
{
    private const MAX_BODY_BYTES = 1048576;

    public function check(array $target): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extension PHP curl absente.');
        }

        $body = '';
        $curl = curl_init((string) $target['url']);
        if ($curl === false) {
            throw new RuntimeException('Initialisation cURL impossible.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => !empty($target['follow_redirects']),
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => min(15, max(1, (int) $target['timeout_seconds'])),
            CURLOPT_TIMEOUT => max(1, (int) $target['timeout_seconds']),
            CURLOPT_SSL_VERIFYPEER => !empty($target['verify_tls']),
            CURLOPT_SSL_VERIFYHOST => !empty($target['verify_tls']) ? 2 : 0,
            CURLOPT_CERTINFO => true,
            CURLOPT_USERAGENT => 'MyServerManager-URLMonitor/1.0',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/json;q=0.9,*/*;q=0.8'],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                $remaining = self::MAX_BODY_BYTES - strlen($body);
                if ($remaining > 0) {
                    $body .= substr($chunk, 0, $remaining);
                }
                return strlen($chunk);
            },
        ];
        if (defined('CURLOPT_PROTOCOLS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        if (defined('CURLOPT_REDIR_PROTOCOLS')) {
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        curl_setopt_array($curl, $options);

        $executed = curl_exec($curl);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        $info = curl_getinfo($curl);
        $certificateInfo = curl_getinfo($curl, CURLINFO_CERTINFO);
        curl_close($curl);

        $httpStatus = (int) ($info['http_code'] ?? 0);
        $contentMatched = null;
        $expectedContent = (string) ($target['expected_content'] ?? '');
        if ($expectedContent !== '' && $executed !== false) {
            $contentMatched = str_contains($body, $expectedContent);
        }

        [$certificateExpiresAt, $certificateExpiryDays] = $this->certificateExpiry($certificateInfo);
        $errorType = null;
        $errorMessage = null;
        if ($executed === false) {
            $errorType = $this->curlErrorType($errno);
            $errorMessage = $error !== '' ? $error : 'Echec de la requete HTTP.';
        } elseif (!$this->statusAccepted($httpStatus, (string) $target['expected_status_codes'])) {
            $errorType = 'http_status';
            $errorMessage = 'Code HTTP ' . $httpStatus . ' non attendu.';
        } elseif ($contentMatched === false) {
            $errorType = 'content_mismatch';
            $errorMessage = 'Le contenu attendu est absent de la reponse.';
        }

        $isHttps = strtolower((string) parse_url((string) $target['url'], PHP_URL_SCHEME)) === 'https';
        $connectSeconds = (float) ($info['connect_time'] ?? 0);
        $appConnectSeconds = (float) ($info['appconnect_time'] ?? 0);

        return [
            'success' => $errorType === null,
            'http_status' => $httpStatus > 0 ? $httpStatus : null,
            'error_type' => $errorType,
            'error_message' => $errorMessage !== null ? mb_substr($errorMessage, 0, 500) : null,
            'dns_ms' => $this->milliseconds($info['namelookup_time'] ?? null),
            'connect_ms' => $this->milliseconds($connectSeconds),
            'tls_ms' => $isHttps && $appConnectSeconds > 0
                ? round(max(0, $appConnectSeconds - $connectSeconds) * 1000, 2)
                : null,
            'ttfb_ms' => $this->milliseconds($info['starttransfer_time'] ?? null),
            'total_ms' => $this->milliseconds($info['total_time'] ?? null),
            'final_url' => mb_substr((string) ($info['url'] ?? $target['url']), 0, 2048),
            'redirect_count' => (int) ($info['redirect_count'] ?? 0),
            'tls_valid' => $isHttps ? ($executed !== false ? 1 : ($errorType === 'tls' ? 0 : null)) : null,
            'certificate_expires_at' => $certificateExpiresAt,
            'certificate_expiry_days' => $certificateExpiryDays,
            'content_matched' => $contentMatched === null ? null : ($contentMatched ? 1 : 0),
        ];
    }

    public function statusAccepted(int $status, string $expression): bool
    {
        foreach (preg_split('/\s*,\s*/', $expression) ?: [] as $part) {
            if (str_contains($part, '-')) {
                [$start, $end] = array_map('intval', explode('-', $part, 2));
                if ($status >= $start && $status <= $end) {
                    return true;
                }
            } elseif ($status === (int) $part) {
                return true;
            }
        }

        return false;
    }

    private function curlErrorType(int $errno): string
    {
        return match ($errno) {
            CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_RESOLVE_PROXY => 'dns',
            CURLE_COULDNT_CONNECT => 'connect',
            CURLE_OPERATION_TIMEDOUT => 'timeout',
            CURLE_SSL_CONNECT_ERROR, CURLE_PEER_FAILED_VERIFICATION, CURLE_SSL_CERTPROBLEM => 'tls',
            CURLE_TOO_MANY_REDIRECTS => 'redirect',
            default => 'network',
        };
    }

    private function certificateExpiry(mixed $certificateInfo): array
    {
        if (!is_array($certificateInfo) || !isset($certificateInfo[0]) || !is_array($certificateInfo[0])) {
            return [null, null];
        }

        $raw = $certificateInfo[0]['Expire date']
            ?? $certificateInfo[0]['Expire Date']
            ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return [null, null];
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return [null, null];
        }

        return [
            date('Y-m-d H:i:s', $timestamp),
            (int) floor(($timestamp - time()) / 86400),
        ];
    }

    private function milliseconds(mixed $seconds): ?float
    {
        if ($seconds === null || !is_numeric($seconds)) {
            return null;
        }

        return round((float) $seconds * 1000, 2);
    }
}
