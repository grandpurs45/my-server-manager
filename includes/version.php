<?php
function getVersionFromPackageJson() {
    $packageFile = __DIR__ . '/../package.json';
    if (file_exists($packageFile)) {
        $json = json_decode(file_get_contents($packageFile), true);
        return $json['version'] ?? 'unknown';
    }
    return 'unknown';
}