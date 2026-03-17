<?php
declare(strict_types=1);

/**
 * Loads key=value pairs from /.env into $_ENV and putenv().
 * Silently skips commented lines, blank lines, and malformed lines.
 */
(function (): void {
    $path = dirname(__DIR__) . '/.env';
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$key, $val] = array_map('trim', explode('=', $line, 2));

        // Strip surrounding single or double quotes
        if (preg_match('/^(["\'])(.*)\\1$/', $val, $m)) {
            $val = $m[2];
        }

        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $val;
            putenv("{$key}={$val}");
        }
    }
})();
