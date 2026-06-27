<?php

return [
    'hana_host' => env('SAP_HANA_HOST', 'localhost'),
    'hana_port' => env('SAP_HANA_PORT', '30015'),
    'hana_username' => env('SAP_HANA_USERNAME', 'SYSTEM'),
    'hana_password' => env('SAP_HANA_PASSWORD', ''),
    'odata_url' => env('SAP_HANA_ODATA_URL', ''),
    'use_odbc' => env('SAP_HANA_USE_ODBC', false),
    'sync_enabled' => env('SAP_HANA_SYNC_ENABLED', false),
    'sync_interval' => env('SAP_HANA_SYNC_INTERVAL', 5),
];
