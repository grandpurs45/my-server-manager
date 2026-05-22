<?php

function msmLoadEnv(?string $path = null): void {
    static $loaded = [];

    $envPath = $path ?? __DIR__ . '/../.env';
    if (isset($loaded[$envPath])) {
        return;
    }

    $loaded[$envPath] = true;

    if (!is_file($envPath) || !is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if ($name === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv("$name=$value");
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

function msmEnv(string $key, ?string $default = null): ?string {
    msmLoadEnv();

    $value = getenv($key);
    if ($value === false) {
        return $default;
    }

    return $value;
}
