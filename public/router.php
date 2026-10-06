<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$decodedPath = is_string($path) ? rawurldecode($path) : '/';
$publicRoot = realpath(__DIR__);
if ($publicRoot !== false && !str_contains($decodedPath, "\0") && !str_contains($decodedPath, '\\')) {
    $candidate = realpath($publicRoot . DIRECTORY_SEPARATOR . ltrim($decodedPath, '/'));
    if ($candidate !== false && is_file($candidate)) {
        $rootPrefix = rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $compareRoot = DIRECTORY_SEPARATOR === '\\' ? strtolower($rootPrefix) : $rootPrefix;
        $compareCandidate = DIRECTORY_SEPARATOR === '\\' ? strtolower($candidate) : $candidate;
        if (str_starts_with($compareCandidate, $compareRoot)
            && $candidate !== realpath(__FILE__)
        ) {
            return false;
        }
    }
}

require __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
