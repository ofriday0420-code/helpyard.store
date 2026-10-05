<?php

$storeId = getenv('SSLCOMMERZ_STORE_ID') ?: '';
$storePassword = getenv('SSLCOMMERZ_STORE_PASSWORD') ?: '';
$sandbox = filter_var(getenv('SSLCOMMERZ_SANDBOX') ?: 'true', FILTER_VALIDATE_BOOLEAN);

return [
    'provider' => 'sslcommerz',
    'enabled' => $storeId !== '' && $storePassword !== '',
    'store_id' => $storeId,
    'store_password' => $storePassword,
    'sandbox' => $sandbox,
    'app_url' => getenv('APP_URL') ?: 'http://localhost:8000',
];
