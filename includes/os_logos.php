<?php

function msmOsLogoSlug(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}

function msmOsLogoDirectory(): string
{
    return dirname(__DIR__) . '/assets/logos/os';
}

function msmOsLogoRelativeDirectory(): string
{
    return 'assets/logos/os';
}

function msmOsLogoReservedSlugs(): array
{
    return [
        'logo-msm',
        'msm-192',
        'msm-512',
        'splash-1536x2048',
        'splash-2048x1536',
        'splash-2732',
    ];
}

function msmOsLogoFiles(): array
{
    $root = dirname(__DIR__);
    $directories = [
        $root . '/assets/logos/os',
        $root . '/assets/logos',
    ];
    $extensions = ['png', 'svg', 'webp'];
    $reserved = array_flip(msmOsLogoReservedSlugs());
    $files = [];

    foreach ($directories as $index => $directory) {
        if (!is_dir($directory)) {
            continue;
        }

        foreach ($extensions as $extension) {
            foreach (glob($directory . '/*.' . $extension) ?: [] as $path) {
                $slug = msmOsLogoSlug(pathinfo($path, PATHINFO_FILENAME));
                if ($slug === '' || isset($reserved[$slug])) {
                    continue;
                }

                if (!isset($files[$slug]) || $index === 0) {
                    $files[$slug] = $path;
                }
            }
        }
    }

    ksort($files);

    return $files;
}

function msmOsLogoUrl(string $osName, string $targetType, string $baseUrl): string
{
    $baseUrl = rtrim($baseUrl, '/') . '/';
    $osSlug = msmOsLogoSlug($osName);
    $targetSlug = msmOsLogoSlug($targetType);
    $logos = msmOsLogoFiles();

    $preferred = array_values(array_unique(array_filter([
        msmOsLogoFamilySlug($osName),
        $osSlug,
        $targetSlug,
    ])));

    foreach ($preferred as $slug) {
        if (isset($logos[$slug])) {
            return msmOsLogoFileToUrl($logos[$slug], $baseUrl);
        }
    }

    foreach ($logos as $slug => $path) {
        if ($slug !== 'linux' && $osSlug !== '' && str_contains($osSlug, $slug)) {
            return msmOsLogoFileToUrl($path, $baseUrl);
        }
    }

    if (in_array($targetSlug, ['linux', 'proxmox', 'docker', 'home-assistant'], true) && isset($logos['linux'])) {
        return msmOsLogoFileToUrl($logos['linux'], $baseUrl);
    }

    return $baseUrl . 'assets/logos/unknown.png';
}

function msmOsLogoFamilySlug(string $osName): ?string
{
    $normalized = msmOsLogoSlug($osName);
    if ($normalized === '') {
        return null;
    }

    $families = [
        'alpine' => ['alpine'],
        'debian' => ['debian'],
        'ubuntu' => ['ubuntu'],
        'windows' => ['windows', 'microsoft-windows'],
        'rocky' => ['rocky', 'rocky-linux'],
        'proxmox' => ['proxmox'],
        'docker' => ['docker'],
        'home-assistant' => ['home-assistant', 'homeassistant', 'haos'],
        'synology' => ['synology', 'dsm'],
        'freebsd' => ['freebsd'],
        'fedora' => ['fedora'],
        'redhat' => ['red-hat', 'redhat', 'rhel'],
        'centos' => ['centos'],
        'almalinux' => ['almalinux', 'alma-linux'],
        'archlinux' => ['archlinux', 'arch-linux'],
        'opensuse' => ['opensuse', 'open-suse'],
    ];

    foreach ($families as $slug => $patterns) {
        foreach ($patterns as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return $slug;
            }
        }
    }

    return null;
}

function msmOsLogoFileToUrl(string $path, string $baseUrl): string
{
    $root = str_replace('\\', '/', dirname(__DIR__));
    $normalizedPath = str_replace('\\', '/', $path);
    $relative = ltrim(str_replace($root, '', $normalizedPath), '/');

    return rtrim($baseUrl, '/') . '/' . $relative;
}

function msmOsLogoEntries(string $baseUrl): array
{
    $entries = [];
    foreach (msmOsLogoFiles() as $slug => $path) {
        $entries[] = [
            'slug' => $slug,
            'url' => msmOsLogoFileToUrl($path, $baseUrl),
            'file' => basename($path),
            'custom' => str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', msmOsLogoDirectory())),
        ];
    }

    return $entries;
}

function msmOsLogoRemoteSources(string $query): array
{
    $slug = msmOsLogoSlug($query);
    $familySlug = msmOsLogoFamilySlug($query);
    $sources = [
        'alpine' => 'alpinelinux',
        'debian' => 'debian',
        'ubuntu' => 'ubuntu',
        'windows' => 'windows',
        'rocky' => 'rockylinux',
        'proxmox' => 'proxmox',
        'docker' => 'docker',
        'home-assistant' => 'homeassistant',
        'synology' => 'synology',
        'freebsd' => 'freebsd',
        'fedora' => 'fedora',
        'redhat' => 'redhat',
        'centos' => 'centos',
        'almalinux' => 'almalinux',
        'archlinux' => 'archlinux',
        'opensuse' => 'opensuse',
    ];
    $candidates = [];

    foreach (array_filter([$familySlug, $slug]) as $candidate) {
        if (isset($sources[$candidate])) {
            $candidates[$candidate] = $sources[$candidate];
        }
    }

    foreach (msmOsLogoRemoteGuessSlugs($slug) as $localSlug => $remoteSlug) {
        if ($localSlug !== '' && $remoteSlug !== '') {
            $candidates[$localSlug] ??= $remoteSlug;
        }
    }

    return $candidates;
}

function msmOsLogoRemoteGuessSlugs(string $slug): array
{
    if ($slug === '') {
        return [];
    }

    $tokens = array_values(array_filter(explode('-', $slug), static function (string $token): bool {
        if ($token === '' || preg_match('/^\d+$/', $token)) {
            return false;
        }

        return !in_array($token, [
            'linux',
            'gnu',
            'os',
            'lts',
            'server',
            'desktop',
            'professional',
            'professionnel',
            'edition',
            'release',
            'core',
            'supervisor',
            've',
        ], true);
    }));

    $compact = implode('', $tokens);
    $firstToken = $tokens[0] ?? '';
    $guesses = [
        $slug => $slug,
        $compact => $compact,
        $firstToken => $firstToken,
    ];

    return array_filter($guesses, static fn (string $remoteSlug): bool => $remoteSlug !== '');
}

function msmOsLogoDownload(string $url): ?string
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl !== false) {
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_USERAGENT => 'MyServerManager-OSLogoFetcher',
            ]);
            $content = curl_exec($curl);
            $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);

            if ($statusCode >= 200 && $statusCode < 300 && is_string($content)) {
                return $content;
            }
        }
    }

    if (!ini_get('allow_url_fopen')) {
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'follow_location' => 1,
            'user_agent' => 'MyServerManager-OSLogoFetcher',
        ],
    ]);
    $content = @file_get_contents($url, false, $context);

    return is_string($content) ? $content : null;
}

function msmOsLogoValidateSvg(string $content): bool
{
    if (strlen($content) > 256 * 1024) {
        return false;
    }

    $trimmed = ltrim($content);
    if (!str_starts_with($trimmed, '<svg') && !str_starts_with($trimmed, '<?xml')) {
        return false;
    }

    $lower = strtolower($content);
    $blockedPatterns = [
        '<script',
        'javascript:',
        ' onload=',
        ' onclick=',
        ' onerror=',
        '<foreignobject',
    ];

    foreach ($blockedPatterns as $pattern) {
        if (str_contains($lower, $pattern)) {
            return false;
        }
    }

    return str_contains($lower, '<svg') && str_contains($lower, '</svg>');
}

function msmOsLogoFetchFromInternet(string $query): array
{
    $candidates = msmOsLogoRemoteSources($query);
    if (empty($candidates)) {
        return [
            'success' => false,
            'message' => 'Aucune source connue pour ce logo OS.',
        ];
    }

    $logoDirectory = msmOsLogoDirectory();
    if (!is_dir($logoDirectory) && !mkdir($logoDirectory, 0775, true) && !is_dir($logoDirectory)) {
        return [
            'success' => false,
            'message' => 'Impossible de creer le dossier des logos OS.',
        ];
    }

    $downloadFailed = false;
    $validationFailed = false;
    foreach ($candidates as $localSlug => $remoteSlug) {
        $url = 'https://cdn.simpleicons.org/' . rawurlencode($remoteSlug);
        $content = msmOsLogoDownload($url);
        if ($content === null) {
            $downloadFailed = true;
            continue;
        }

        if (!msmOsLogoValidateSvg($content)) {
            $validationFailed = true;
            continue;
        }

        $destination = $logoDirectory . '/' . $localSlug . '.svg';
        if (is_file($destination)) {
            return [
                'success' => false,
                'message' => 'Un logo personnalise existe deja pour ' . $localSlug . '. Supprime-le ou remplace-le manuellement si necessaire.',
            ];
        }

        if (file_put_contents($destination, $content) === false) {
            return [
                'success' => false,
                'message' => 'Logo trouve mais impossible de l enregistrer.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Logo OS recupere automatiquement : ' . $localSlug . '.svg.',
            'source' => $url,
        ];
    }

    if ($downloadFailed && !$validationFailed) {
        return [
            'success' => false,
            'message' => 'Source connue, mais telechargement impossible depuis ce serveur. Verifie l acces HTTPS sortant ou PHP curl/allow_url_fopen.',
        ];
    }

    return [
        'success' => false,
        'message' => 'Aucun logo valide trouve depuis la source distante.',
    ];
}
