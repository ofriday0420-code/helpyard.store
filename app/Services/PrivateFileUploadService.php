<?php

namespace Helpyard\App\Services;

use RuntimeException;

class PrivateFileUploadService
{
    public const MAX_FILE_SIZE = 52428800;

    private const ALLOWED_FILES = [
        'pdf' => ['application/pdf'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'epub' => ['application/epub+zip', 'application/zip', 'application/x-zip-compressed'],
        'mp4' => ['video/mp4'],
    ];

    public static function validateMetadata(string $fileName, int $fileSize, int $uploadError, string $mimeType): array
    {
        $metadata = self::basicMetadata($fileName, $fileSize, $uploadError);
        if ($metadata['error'] !== null) {
            return $metadata;
        }
        if (!in_array(strtolower($mimeType), self::ALLOWED_FILES[$metadata['extension']], true)) {
            return [
                'download_name' => '',
                'extension' => '',
                'error' => 'Upload a PDF, ZIP, EPUB, or MP4 file whose content matches its file type.',
            ];
        }

        return $metadata;
    }

    public static function storeUploaded(
        array $upload,
        int $productId,
        string $privateRoot,
        string $publicRoot
    ): array {
        $fileName = $upload['name'] ?? null;
        $temporaryPath = $upload['tmp_name'] ?? null;
        $fileSize = $upload['size'] ?? null;
        $uploadError = $upload['error'] ?? null;
        if (!is_string($fileName) || !is_string($temporaryPath)
            || !is_int($fileSize) || !is_int($uploadError)
        ) {
            throw new RuntimeException('The uploaded file is invalid. Please choose the file again.');
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            $metadata = self::validateMetadata($fileName, $fileSize, $uploadError, '');
            throw new RuntimeException($metadata['error']);
        }
        if (!is_uploaded_file($temporaryPath)) {
            throw new RuntimeException('The uploaded file is invalid. Please choose the file again.');
        }
        $metadata = self::basicMetadata($fileName, $fileSize, $uploadError);
        if ($metadata['error'] !== null) {
            throw new RuntimeException($metadata['error']);
        }
        if (!class_exists(\finfo::class)) {
            throw new RuntimeException('Enable the PHP fileinfo extension to inspect uploaded file types.');
        }

        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($temporaryPath);
        if (!is_string($mimeType)) {
            throw new RuntimeException('The uploaded file type could not be verified.');
        }
        $metadata = self::validateMetadata($fileName, $fileSize, $uploadError, $mimeType);
        if ($metadata['error'] !== null) {
            throw new RuntimeException($metadata['error']);
        }

        $root = realpath($privateRoot);
        $webRoot = realpath($publicRoot);
        if ($productId < 1 || $root === false || $webRoot === false || !is_dir($root)) {
            throw new RuntimeException('Private file storage is not configured.');
        }

        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $webPrefix = rtrim($webRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $compareRoot = DIRECTORY_SEPARATOR === '\\' ? strtolower($rootPrefix) : $rootPrefix;
        $compareWeb = DIRECTORY_SEPARATOR === '\\' ? strtolower($webPrefix) : $webPrefix;
        if (str_starts_with($compareRoot, $compareWeb) || str_starts_with($compareWeb, $compareRoot)) {
            throw new RuntimeException('Private file storage must be separate from the public web directory.');
        }

        $productDirectory = $root . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . $productId;
        if (!is_dir($productDirectory) && !mkdir($productDirectory, 0750, true) && !is_dir($productDirectory)) {
            throw new RuntimeException('The private product file directory could not be created.');
        }
        $resolvedDirectory = realpath($productDirectory);
        $compareDirectory = $resolvedDirectory === false
            ? ''
            : (DIRECTORY_SEPARATOR === '\\' ? strtolower($resolvedDirectory . DIRECTORY_SEPARATOR) : $resolvedDirectory . DIRECTORY_SEPARATOR);
        if ($resolvedDirectory === false || !str_starts_with($compareDirectory, $compareRoot)) {
            throw new RuntimeException('The private product file directory is outside private storage.');
        }

        $storageName = bin2hex(random_bytes(24)) . '.' . $metadata['extension'];
        $destination = $resolvedDirectory . DIRECTORY_SEPARATOR . $storageName;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException('The uploaded file could not be moved into private storage.');
        }

        return [
            'storage_key' => 'products/' . $productId . '/' . $storageName,
            'download_name' => $metadata['download_name'],
            'file_size' => $fileSize,
            'mime_type' => strtolower($mimeType),
            'absolute_path' => $destination,
        ];
    }

    private static function uploadErrorMessage(int $uploadError): string
    {
        return match ($uploadError) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The selected file exceeds the server upload limit.',
            UPLOAD_ERR_PARTIAL => 'The file upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Choose a file to upload.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION =>
                'The server could not receive the file. Please contact the site administrator.',
            default => 'The uploaded file is invalid.',
        };
    }

    private static function basicMetadata(string $fileName, int $fileSize, int $uploadError): array
    {
        if ($uploadError !== UPLOAD_ERR_OK) {
            return ['download_name' => '', 'extension' => '', 'error' => self::uploadErrorMessage($uploadError)];
        }

        $downloadName = DigitalDeliveryService::safeDownloadName($fileName);
        $nameLength = preg_match_all('/./us', $downloadName);
        if ($nameLength === false || $nameLength > 255) {
            return ['download_name' => '', 'extension' => '', 'error' => 'Use a file name up to 255 characters.'];
        }
        if ($fileSize < 1 || $fileSize > self::MAX_FILE_SIZE) {
            return ['download_name' => '', 'extension' => '', 'error' => 'Files must be between 1 byte and 50 MB.'];
        }

        $extension = strtolower(pathinfo($downloadName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_FILES[$extension])) {
            return [
                'download_name' => '',
                'extension' => '',
                'error' => 'Upload a PDF, ZIP, EPUB, or MP4 file whose content matches its file type.',
            ];
        }

        return ['download_name' => $downloadName, 'extension' => $extension, 'error' => null];
    }
}
