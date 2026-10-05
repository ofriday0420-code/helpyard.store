<?php

return [
    'name' => 'Helpyard.store',
    'env' => getenv('APP_ENV') ?: 'local',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'base_url' => getenv('APP_URL') ?: 'http://localhost:8000',
    'private_storage' => getenv('PRIVATE_STORAGE_PATH')
        ?: dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private',
];
