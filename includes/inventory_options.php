<?php

use MSM\SettingsManager;

function msmInventoryDefaultOptions(string $key): array
{
    return match ($key) {
        'target_types' => [
            'linux' => 'Linux',
            'windows' => 'Windows',
            'proxmox' => 'Proxmox',
            'synology' => 'Synology',
            'docker' => 'Docker',
            'website' => 'Site web',
            'network' => 'Equipement reseau',
            'other' => 'Autre',
        ],
        'environments' => [
            'production' => 'Production',
            'homelab' => 'Homelab',
            'staging' => 'Staging',
            'development' => 'Developpement',
            'test' => 'Test',
            'other' => 'Autre',
        ],
        'criticalities' => [
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            'critical' => 'Critique',
        ],
        'collection_methods' => [
            'ssh' => 'SSH',
            'ping' => 'Ping uniquement',
            'api' => 'API',
            'winrm' => 'WinRM',
            'manual' => 'Manuelle',
            'none' => 'Aucune',
        ],
        default => [],
    };
}

function msmInventoryParseOptions(?string $raw, array $fallback): array
{
    if ($raw === null || trim($raw) === '') {
        return $fallback;
    }

    $options = [];
    foreach (preg_split('/\R/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_contains($line, '=')) {
            [$value, $label] = explode('=', $line, 2);
            $value = msmInventoryNormalizeOptionValue($value);
            $label = trim($label);
        } else {
            $label = $line;
            $value = msmInventoryNormalizeOptionValue($line);
        }

        if ($value !== '' && $label !== '') {
            $options[$value] = $label;
        }
    }

    return $options ?: $fallback;
}

function msmInventoryNormalizeOptionValue(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '_', $value) ?? '';
    return trim($value, '_-');
}

function msmInventoryOptions(SettingsManager $settingsManager, string $key): array
{
    return msmInventoryParseOptions(
        $settingsManager->get('inventaire', $key),
        msmInventoryDefaultOptions($key)
    );
}

function msmInventoryNormalizeSelected(string $value, array $options, string $fallback): string
{
    return array_key_exists($value, $options) ? $value : $fallback;
}
