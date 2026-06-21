<?php
namespace MSM;

class UpdateChecker
{
    private const CATEGORY = 'msm_update';
    private const CACHE_TTL_SECONDS = 21600;
    private const RELEASE_API_URL = 'https://api.github.com/repos/grandpurs45/my-server-manager/releases/latest';
    private const TAGS_API_URL = 'https://api.github.com/repos/grandpurs45/my-server-manager/tags';
    private const TAG_RELEASE_URL_PREFIX = 'https://github.com/grandpurs45/my-server-manager/releases/tag/';

    public function __construct(private SettingsManager $settingsManager)
    {
    }

    public function status(string $currentVersion): array
    {
        $currentVersion = $this->normalizeVersion($currentVersion);
        $cached = $this->cachedStatus($currentVersion);

        if ($this->cacheIsFresh($cached['checked_at'])) {
            return $cached;
        }

        $latest = $this->fetchLatestVersion();
        if ($latest === null) {
            $this->settingsManager->set(self::CATEGORY, 'last_error', 'Impossible de recuperer la derniere version.');
            $this->settingsManager->set(self::CATEGORY, 'checked_at', date('c'));
            return $this->cachedStatus($currentVersion);
        }

        $latestVersion = $latest['version'];
        $releaseUrl = $latest['url'];

        $this->settingsManager->set(self::CATEGORY, 'latest_version', $latestVersion);
        $this->settingsManager->set(self::CATEGORY, 'release_url', $releaseUrl);
        $this->settingsManager->set(self::CATEGORY, 'checked_at', date('c'));
        $this->settingsManager->set(self::CATEGORY, 'last_error', '');

        return $this->buildStatus($currentVersion, $latestVersion, $releaseUrl, date('c'), '');
    }

    private function cachedStatus(string $currentVersion): array
    {
        return $this->buildStatus(
            $currentVersion,
            $this->normalizeVersion((string) ($this->settingsManager->get(self::CATEGORY, 'latest_version') ?? '')),
            (string) ($this->settingsManager->get(self::CATEGORY, 'release_url') ?? ''),
            (string) ($this->settingsManager->get(self::CATEGORY, 'checked_at') ?? ''),
            (string) ($this->settingsManager->get(self::CATEGORY, 'last_error') ?? '')
        );
    }

    private function buildStatus(
        string $currentVersion,
        string $latestVersion,
        string $releaseUrl,
        string $checkedAt,
        string $error
    ): array {
        $updateAvailable = $currentVersion !== ''
            && $latestVersion !== ''
            && version_compare($latestVersion, $currentVersion, '>');

        return [
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'release_url' => $releaseUrl,
            'checked_at' => $checkedAt,
            'error' => $error,
            'update_available' => $updateAvailable,
        ];
    }

    private function cacheIsFresh(string $checkedAt): bool
    {
        if ($checkedAt === '') {
            return false;
        }

        $timestamp = strtotime($checkedAt);
        if ($timestamp === false) {
            return false;
        }

        return time() - $timestamp < self::CACHE_TTL_SECONDS;
    }

    private function fetchLatestVersion(): ?array
    {
        return $this->fetchLatestRelease() ?? $this->fetchLatestTag();
    }

    private function fetchLatestRelease(): ?array
    {
        $json = $this->fetchUrl(self::RELEASE_API_URL);
        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['tag_name'])) {
            return null;
        }

        return [
            'version' => $this->normalizeVersion((string) $data['tag_name']),
            'url' => (string) ($data['html_url'] ?? ''),
        ];
    }

    private function fetchLatestTag(): ?array
    {
        $json = $this->fetchUrl(self::TAGS_API_URL);
        if ($json === null) {
            return null;
        }

        $tags = json_decode($json, true);
        if (!is_array($tags)) {
            return null;
        }

        $latestVersion = '';
        $latestTagName = '';

        foreach ($tags as $tag) {
            if (!is_array($tag) || empty($tag['name'])) {
                continue;
            }

            $tagName = (string) $tag['name'];
            $version = $this->normalizeVersion($tagName);
            if (!$this->looksLikeVersion($version)) {
                continue;
            }

            if ($latestVersion === '' || version_compare($version, $latestVersion, '>')) {
                $latestVersion = $version;
                $latestTagName = $tagName;
            }
        }

        if ($latestVersion === '') {
            return null;
        }

        return [
            'version' => $latestVersion,
            'url' => self::TAG_RELEASE_URL_PREFIX . rawurlencode($latestTagName),
        ];
    }

    private function fetchUrl(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_USERAGENT => 'MSM-UpdateChecker',
                CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
            ]);

            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            return is_string($body) && $status >= 200 && $status < 300 ? $body : null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 3,
                'header' => "User-Agent: MSM-UpdateChecker\r\nAccept: application/vnd.github+json\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        return is_string($body) ? $body : null;
    }

    private function normalizeVersion(string $version): string
    {
        $version = trim($version);
        if (str_starts_with(strtolower($version), 'v')) {
            $version = substr($version, 1);
        }

        return $version;
    }

    private function looksLikeVersion(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) === 1;
    }
}
