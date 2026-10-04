<?php

declare(strict_types=1);

namespace PracticeApi;

/**
 * Reads the Laravel app's .env so both systems use the same database settings.
 * Real environment variables win over the file, as in Laravel.
 */
final class Env
{
    /** @var array<string, string> */
    private array $values = [];

    public function __construct(string $envFile)
    {
        if (! is_readable($envFile)) {
            return;
        }
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (str_starts_with($key, 'export ')) {
                $key = trim(substr($key, 7));
            }
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            } else {
                $value = trim(explode(' #', $value, 2)[0]);
            }
            $this->values[$key] = $value;
        }
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $real = getenv($key);
        if ($real !== false) {
            return $real;
        }

        return $this->values[$key] ?? $default;
    }
}
