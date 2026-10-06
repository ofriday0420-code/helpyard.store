<?php

namespace Helpyard\App\Services;

use RuntimeException;

class ProductImageUploadService
{
    public const MAX_FILE_SIZE = 5_242_880;
    public const MAX_IMAGES_PER_PRODUCT = 12;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function store(array $upload, int $productId, string $publicRoot): array
    {
        $name = $upload['name'] ?? null;
        $temporaryPath = $upload['tmp_name'] ?? null;
        $size = $upload['size'] ?? null;
        $error = $upload['error'] ?? null;
        if (!is_string($name) || !is_string($temporaryPath) || !is_int($size) || !is_int($error)) {
            throw new RuntimeException('Choose a valid image file.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::uploadErrorMessage($error));
        }
        if (!is_uploaded_file($temporaryPath)) {
            throw new RuntimeException('The image upload could not be verified. Please choose the file again.');
        }
        if ($size < 1 || $size > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Product images must be between 1 byte and 5 MB.');
        }
        if ($productId < 1 || !class_exists(\finfo::class)) {
            throw new RuntimeException('Image storage or the PHP fileinfo extension is not available.');
        }

        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($temporaryPath);
        if (!is_string($mimeType) || !isset(self::MIME_EXTENSIONS[strtolower($mimeType)])) {
            throw new RuntimeException('Upload a JPEG, PNG, or WebP image.');
        }
        $imageInfo = @getimagesize($temporaryPath);
        if (!is_array($imageInfo) || strtolower((string) ($imageInfo['mime'] ?? '')) !== strtolower($mimeType)) {
            throw new RuntimeException('The uploaded content is not a valid image.');
        }
        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if ($width < 1 || $height < 1 || $width > 8000 || $height > 8000 || $width * $height > 40_000_000) {
            throw new RuntimeException('Images must be no larger than 8,000 pixels per side or 40 megapixels.');
        }

        $root = realpath($publicRoot);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Public image storage is not configured.');
        }
        $imageDirectory = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
        if (!is_dir($imageDirectory) && !mkdir($imageDirectory, 0755, true) && !is_dir($imageDirectory)) {
            throw new RuntimeException('The product image directory could not be created.');
        }
        $resolvedDirectory = realpath($imageDirectory);
        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $directoryPrefix = $resolvedDirectory === false
            ? ''
            : rtrim($resolvedDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $compareRoot = DIRECTORY_SEPARATOR === '\\' ? strtolower($rootPrefix) : $rootPrefix;
        $compareDirectory = DIRECTORY_SEPARATOR === '\\' ? strtolower($directoryPrefix) : $directoryPrefix;
        if ($resolvedDirectory === false || !str_starts_with($compareDirectory, $compareRoot)) {
            throw new RuntimeException('The product image directory is outside the public web root.');
        }

        $storageName = bin2hex(random_bytes(24)) . '.' . self::MIME_EXTENSIONS[strtolower($mimeType)];
        $destination = $resolvedDirectory . DIRECTORY_SEPARATOR . $storageName;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException('The product image could not be saved.');
        }

        return [
            'image_url' => '/uploads/products/' . $storageName,
            'absolute_path' => $destination,
            'mime_type' => strtolower($mimeType),
            'file_size' => $size,
            'width' => $width,
            'height' => $height,
        ];
    }

    public static function removeManagedImage(string $imageUrl, string $publicRoot): void
    {
        if (preg_match('~^/uploads/products/[a-f0-9]{48}\.(?:jpg|png|webp)$~', $imageUrl) !== 1) {
            return;
        }

        $root = realpath($publicRoot);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('Public image storage is not configured.');
        }
        $directory = realpath($root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products');
        if ($directory === false) {
            return;
        }
        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $directoryPrefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $compareRoot = DIRECTORY_SEPARATOR === '\\' ? strtolower($rootPrefix) : $rootPrefix;
        $compareDirectory = DIRECTORY_SEPARATOR === '\\' ? strtolower($directoryPrefix) : $directoryPrefix;
        if (!str_starts_with($compareDirectory, $compareRoot)) {
            throw new RuntimeException('The product image path is outside the public web root.');
        }

        $path = $directory . DIRECTORY_SEPARATOR . substr($imageUrl, strlen('/uploads/products/'));
        $resolvedPath = realpath($path);
        if ($resolvedPath === false) {
            return;
        }
        $comparePath = DIRECTORY_SEPARATOR === '\\' ? strtolower($resolvedPath) : $resolvedPath;
        if (!str_starts_with($comparePath, $compareDirectory) || !is_file($resolvedPath)) {
            throw new RuntimeException('The product image path is outside its storage directory.');
        }
        if (!unlink($resolvedPath)) {
            throw new RuntimeException('The product image file could not be removed.');
        }
    }

    private static function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The selected image exceeds the server upload limit.',
            UPLOAD_ERR_PARTIAL => 'The image upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Choose an image to upload.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION =>
                'The server could not receive the image. Please contact the site administrator.',
            default => 'The image upload is invalid.',
        };
    }
}
