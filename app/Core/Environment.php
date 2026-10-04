<?php

namespace Helpyard\App\Core;

use RuntimeException;

class Environment
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        if (!is_readable($path)) {
            throw new RuntimeException('The environment file is not readable.');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('The environment file could not be read.');
        }

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                throw new RuntimeException('Invalid environment entry on line ' . ($index + 1) . '.');
            }

            $name = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                throw new RuntimeException('Invalid environment variable name on line ' . ($index + 1) . '.');
            }

            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
                if ($value[strlen($value) - 1] !== $value[0]) {
                    throw new RuntimeException('Unmatched quote in environment entry on line ' . ($index + 1) . '.');
                }

                $value = substr($value, 1, -1);
            } elseif ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                throw new RuntimeException('Unmatched quote in environment entry on line ' . ($index + 1) . '.');
            }

            if (getenv($name) !== false) {
                continue;
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
