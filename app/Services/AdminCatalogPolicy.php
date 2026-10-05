<?php

namespace Helpyard\App\Services;

use Helpyard\App\Repositories\CatalogRepository;

class AdminCatalogPolicy
{
    public static function validateCategory(array $input): array
    {
        $name = self::text($input['name'] ?? null, 120);
        $slug = self::slug($input['slug'] ?? null, $name, 140);
        if ($name === null) {
            return ['data' => null, 'error' => 'Enter a category name up to 120 characters.'];
        }
        if ($slug === null) {
            return ['data' => null, 'error' => 'Enter a URL slug using lowercase letters, numbers, and hyphens.'];
        }

        return ['data' => ['name' => $name, 'slug' => $slug], 'error' => null];
    }

    public static function validateProduct(array $input): array
    {
        $name = self::text($input['name'] ?? null, 180);
        $slug = self::slug($input['slug'] ?? null, $name, 180);
        $productType = $input['product_type'] ?? null;
        $shortDescription = self::text($input['short_description'] ?? '', 1000, true);
        $description = self::text($input['description'] ?? '', 20000, true);
        $price = self::money($input['price'] ?? null);
        $comparePriceValue = is_string($input['compare_price'] ?? null)
            ? trim($input['compare_price'])
            : $input['compare_price'] ?? null;
        $comparePrice = $comparePriceValue === '' || $comparePriceValue === null
            ? null
            : self::money($comparePriceValue);
        $stock = self::stock($input['stock_quantity'] ?? null);
        $categoryValue = $input['category_id'] ?? '';
        $categoryId = $categoryValue === '' || $categoryValue === null
            ? null
            : (is_string($categoryValue) || is_int($categoryValue)
                ? filter_var($categoryValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false);
        $isActive = ($input['is_active'] ?? null) === '1' || ($input['is_active'] ?? null) === 1;

        if ($name === null) {
            return ['data' => null, 'error' => 'Enter a product name up to 180 characters.'];
        }
        if ($slug === null) {
            return ['data' => null, 'error' => 'Enter a URL slug using lowercase letters, numbers, and hyphens.'];
        }
        if (!is_string($productType) || !in_array($productType, CatalogRepository::productTypes(), true)) {
            return ['data' => null, 'error' => 'Choose a valid product type.'];
        }
        if ($shortDescription === null || $description === null) {
            return ['data' => null, 'error' => 'Descriptions contain invalid text or exceed their character limits.'];
        }
        if ($price === null) {
            return ['data' => null, 'error' => 'Enter a price from 0.00 to 99,999,999.99 BDT.'];
        }
        if ($comparePriceValue !== '' && $comparePrice === null) {
            return ['data' => null, 'error' => 'Enter a valid comparison price or leave it blank.'];
        }
        if ($comparePrice !== null && self::moneyCents($comparePrice) <= self::moneyCents($price)) {
            return ['data' => null, 'error' => 'The comparison price must be greater than the selling price.'];
        }
        if ($stock === null) {
            return ['data' => null, 'error' => 'Enter a stock quantity from 0 to 2,147,483,647.'];
        }
        if ($categoryId === false) {
            return ['data' => null, 'error' => 'Choose a valid category or leave it uncategorized.'];
        }

        return [
            'data' => [
                'name' => $name,
                'slug' => $slug,
                'product_type' => $productType,
                'short_description' => $shortDescription,
                'description' => $description,
                'price' => $price,
                'compare_price' => $comparePrice,
                'stock_quantity' => $stock,
                'category_id' => $categoryId === null ? null : (int) $categoryId,
                'is_active' => $isActive ? 1 : 0,
            ],
            'error' => null,
        ];
    }

    public static function validateStock(mixed $value): ?int
    {
        return self::stock($value);
    }

    private static function text(mixed $value, int $maximum, bool $allowEmpty = false): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        $length = preg_match_all('/./us', $value);
        if (($value === '' && !$allowEmpty) || $length === false || $length > $maximum
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)
        ) {
            return null;
        }

        return $value;
    }

    private static function slug(mixed $value, ?string $name, int $maximum): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));
        if ($value === '' && $name !== null) {
            $asciiName = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
            if (!is_string($asciiName)) {
                return null;
            }
            $value = strtolower($asciiName);
        }
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value, '-');
        if ($value === '' || strlen($value) > $maximum
            || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)
        ) {
            return null;
        }

        return $value;
    }

    private static function money(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $value = trim((string) $value);
        if (!preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/', $value)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return $whole . '.' . str_pad($fraction, 2, '0');
    }

    private static function moneyCents(string $value): int
    {
        [$whole, $fraction] = explode('.', $value, 2);

        return (int) $whole * 100 + (int) $fraction;
    }

    private static function stock(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $stock = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);

        return $stock === false ? null : $stock;
    }
}
