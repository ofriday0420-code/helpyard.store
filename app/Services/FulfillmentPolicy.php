<?php

namespace Helpyard\App\Services;

class FulfillmentPolicy
{
    private const TRANSITIONS = [
        'start_processing' => ['paid', 'processing'],
        'mark_shipped' => ['processing', 'shipped'],
        'mark_delivered' => ['shipped', 'delivered'],
    ];

    public static function nextStatus(string $currentStatus, string $action): ?string
    {
        [$requiredStatus, $nextStatus] = self::TRANSITIONS[$action] ?? ['', ''];

        return $currentStatus === $requiredStatus ? $nextStatus : null;
    }

    public static function validateShipmentDetails(string $carrier, string $trackingNumber): array
    {
        $carrier = trim($carrier);
        $trackingNumber = trim($trackingNumber);
        $carrierLength = preg_match_all('/./us', $carrier);
        $trackingLength = preg_match_all('/./us', $trackingNumber);

        if ($carrier === '' || $carrierLength === false || $carrierLength > 80
            || preg_match('/[\x00-\x1F\x7F]/', $carrier)
        ) {
            return ['carrier' => '', 'tracking_number' => '', 'error' => 'Enter a valid carrier name up to 80 characters.'];
        }
        if ($trackingNumber === '' || $trackingLength === false || $trackingLength > 120
            || preg_match('/[\x00-\x1F\x7F]/', $trackingNumber)
        ) {
            return ['carrier' => '', 'tracking_number' => '', 'error' => 'Enter a valid tracking number up to 120 characters.'];
        }

        return ['carrier' => $carrier, 'tracking_number' => $trackingNumber, 'error' => null];
    }

    public static function canShipProductTypes(array $productTypes): bool
    {
        if ($productTypes === []) {
            return false;
        }

        foreach ($productTypes as $productType) {
            if (!in_array($productType, ['physical', 'book'], true)) {
                return false;
            }
        }

        return true;
    }
}
