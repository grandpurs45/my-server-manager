<?php

function msmStatusBadge(string $state, string $label, string $size = 'xs'): string
{
    $classes = match ($state) {
        'ok' => 'border-green-200 bg-green-50 text-green-700',
        'warning' => 'border-yellow-200 bg-yellow-50 text-yellow-800',
        'critical' => 'border-red-200 bg-red-50 text-red-700',
        'info' => 'border-blue-200 bg-blue-50 text-blue-700',
        'neutral' => 'border-slate-200 bg-slate-50 text-slate-600',
        default => 'border-gray-200 bg-gray-50 text-gray-600',
    };

    $textSize = $size === 'sm' ? 'text-sm' : 'text-xs';

    return '<span class="inline-flex rounded border px-2 py-1 font-semibold '
        . $textSize
        . ' '
        . $classes
        . '">'
        . htmlspecialchars($label)
        . '</span>';
}

function msmStatusStateFromPatch(?string $status): string
{
    return match ($status) {
        'ok' => 'ok',
        'warning', 'unsupported' => 'warning',
        'critical', 'error' => 'critical',
        default => 'unknown',
    };
}

function msmStatusLabelFromPatch(?string $status): string
{
    return match ($status) {
        'ok' => 'OK',
        'warning' => 'Warning',
        'critical' => 'Critical',
        'error' => 'Erreur',
        'unsupported' => 'Non supporte',
        default => 'Unknown',
    };
}

function msmStatusStateFromOsLifecycle(?string $status): string
{
    return match ($status) {
        'supported' => 'ok',
        'eol_soon' => 'warning',
        'eol' => 'critical',
        default => 'unknown',
    };
}

function msmStatusLabelFromOsLifecycle(?string $status): string
{
    return match ($status) {
        'supported' => 'OK',
        'eol_soon' => 'Warning',
        'eol' => 'Critical',
        default => 'Unknown',
    };
}

function msmStatusStateFromSecurity(?string $status): string
{
    return match ($status) {
        'ok' => 'ok',
        'warning' => 'warning',
        'error' => 'critical',
        default => 'unknown',
    };
}

function msmStatusLabelFromSecurity(?string $status): string
{
    return match ($status) {
        'ok' => 'OK',
        'warning' => 'Warning',
        'error' => 'Critical',
        default => 'Unknown',
    };
}
