<?php
/**
 * Environment Variable Loader
 * Loads .env file variables into getenv() and $_ENV.
 * No external dependencies required.
 */

function load_env(string $path = null): void
{
    if ($path === null) {
        $path = dirname(__DIR__) . '/.env';
    }

    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments
        if ($line[0] ?? '' === '#') {
            continue;
        }

        // Skip lines without =
        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value);

        // Remove surrounding quotes
        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            $value = substr($value, 1, -1);
        } elseif (strlen($value) >= 2 && $value[0] === "'" && $value[strlen($value) - 1] === "'") {
            $value = substr($value, 1, -1);
        }

        // Set in environment if not already set
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
        }
        $_ENV[$key] = $value;
    }
}

/**
 * Get an environment variable with a default fallback.
 */
function env(string $key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return $value;
}

// Auto-load .env on include
load_env();
