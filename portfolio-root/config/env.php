<?php
/**
 * config/env.php
 *
 * Minimal, dependency-free .env loader.
 *
 * Reads KEY=VALUE pairs from a .env file at the project root and injects
 * them into getenv()/$_ENV so the rest of the app (database.php,
 * mailer.php) can keep using getenv('DB_HOST') etc. without caring where
 * the value actually came from — an environment variable set by the
 * hosting platform always takes precedence and is never overwritten.
 *
 * This intentionally avoids pulling in a Composer package just for env
 * parsing; if you're already using vlucas/phpdotenv or similar, you can
 * safely delete this file and its require_once calls.
 */

declare(strict_types=1);

function loadEnv(string $path): void
{
    if (!is_readable($path)) {
        return; // No .env file present (e.g. real env vars are set by the host) — that's fine.
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and blank lines.
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Strip matching surrounding quotes, if present.
        if (strlen($value) >= 2 && (
            ($value[0] === '"' && $value[-1] === '"') ||
            ($value[0] === "'" && $value[-1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }

        // Never override a real environment variable already set by the
        // hosting platform (e.g. an environment panel in cPanel/Docker).
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(__DIR__ . '/../.env');
