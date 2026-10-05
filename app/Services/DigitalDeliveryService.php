<?php

namespace Helpyard\App\Services;

use RuntimeException;

class DigitalDeliveryService
{
    public static function resolvePrivateFile(string $privateRoot, string $storageKey, string $publicRoot): string
    {
        if ($storageKey === '' || str_contains($storageKey, "\0")
            || preg_match('/^[A-Za-z]:/', $storageKey)
            || str_contains($storageKey, ':')
            || str_starts_with($storageKey, '/')
            || str_starts_with($storageKey, '\\')
        ) {
            throw new RuntimeException('The private download path is invalid.');
        }

        $segments = preg_split('~[\\\\/]~', $storageKey);
        if ($segments === false || $segments === []) {
            throw new RuntimeException('The private download path is invalid.');
        }
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('The private download path is invalid.');
            }
        }

        $resolvedRoot = realpath($privateRoot);
        $resolvedPublicRoot = realpath($publicRoot);
        if ($resolvedRoot === false || $resolvedPublicRoot === false || !is_dir($resolvedRoot)) {
            throw new RuntimeException('Private download storage is not configured.');
        }

        $rootPrefix = rtrim($resolvedRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $publicPrefix = rtrim($resolvedPublicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $comparisonRoot = DIRECTORY_SEPARATOR === '\\' ? strtolower($rootPrefix) : $rootPrefix;
        $comparisonPublic = DIRECTORY_SEPARATOR === '\\' ? strtolower($publicPrefix) : $publicPrefix;
        if (str_starts_with($comparisonRoot, $comparisonPublic)) {
            throw new RuntimeException('Private download storage must be outside the public directory.');
        }

        $candidate = $resolvedRoot . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
        $resolvedFile = realpath($candidate);
        if ($resolvedFile === false || !is_file($resolvedFile) || !is_readable($resolvedFile)) {
            throw new RuntimeException('The private download file is unavailable.');
        }

        $comparisonFile = DIRECTORY_SEPARATOR === '\\' ? strtolower($resolvedFile) : $resolvedFile;
        if (!str_starts_with($comparisonFile, $comparisonRoot)) {
            throw new RuntimeException('The private download path is outside its storage directory.');
        }
        if (str_starts_with($comparisonFile, $comparisonPublic)) {
            throw new RuntimeException('Files inside the public directory cannot be served as private downloads.');
        }

        return $resolvedFile;
    }

    public static function safeDownloadName(string $downloadName): string
    {
        $name = trim(str_replace(["\0", "\r", "\n"], '', $downloadName));
        $name = basename(str_replace('\\', '/', $name));
        if ($name === '' || $name === '.' || $name === '..') {
            throw new RuntimeException('The download file name is invalid.');
        }

        return $name;
    }
}
