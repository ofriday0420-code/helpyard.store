<?php

namespace Helpyard\App\Core;

use PDO;
use RuntimeException;

class SqlScript
{
    public static function executeFile(PDO $connection, string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('SQL script is missing or unreadable: ' . basename($path));
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Could not read SQL script: ' . basename($path));
        }

        foreach (self::statements($contents) as $statement) {
            $connection->exec($statement);
        }
    }

    public static function statements(string $sql): array
    {
        $statements = [];
        $statement = '';
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            $next = $sql[$index + 1] ?? '';

            if ($lineComment) {
                $statement .= $character;
                if ($character === "\n") {
                    $lineComment = false;
                }
                continue;
            }

            if ($blockComment) {
                $statement .= $character;
                if ($character === '*' && $next === '/') {
                    $statement .= $next;
                    $index++;
                    $blockComment = false;
                }
                continue;
            }

            if ($quote !== null) {
                $statement .= $character;
                if ($character === '\\' && $quote !== '`' && $next !== '') {
                    $statement .= $next;
                    $index++;
                    continue;
                }
                if ($character === $quote) {
                    if ($next === $quote) {
                        $statement .= $next;
                        $index++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
                $statement .= $character;
                continue;
            }

            if ($character === '#' || ($character === '-' && $next === '-' && ctype_space($sql[$index + 2] ?? ' '))) {
                $lineComment = true;
                $statement .= $character;
                if ($character === '-') {
                    $statement .= $next;
                    $index++;
                }
                continue;
            }

            if ($character === '/' && $next === '*') {
                $blockComment = true;
                $statement .= '/*';
                $index++;
                continue;
            }

            if ($character === ';') {
                if (trim($statement) !== '') {
                    $statements[] = trim($statement);
                }
                $statement = '';
                continue;
            }

            $statement .= $character;
        }

        if ($quote !== null || $blockComment) {
            throw new RuntimeException('SQL script contains an unterminated quoted value or block comment.');
        }

        if (trim($statement) !== '') {
            $statements[] = trim($statement);
        }

        return $statements;
    }
}
