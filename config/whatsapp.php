<?php

return [
    'enabled' => env('WHATSAPP_ENABLED', false),
    'mode' => env('WHATSAPP_MODE', 'test'),
    'token' => env('WHATSAPP_TOKEN'),
    'phone_id' => env('WHATSAPP_PHONE_ID'),
    'waba_id' => env('WHATSAPP_WABA_ID'),
    'sender' => env('WHATSAPP_SENDER'),
    'version' => env('WHATSAPP_GRAPH_VERSION'),
    'timeout' => (int) env('WHATSAPP_TIMEOUT', 20),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'allowlist' => array_values(array_filter(array_map('trim', explode(',', env('WHATSAPP_TEST_RECIPIENTS', ''))))),
    'templates' => [
        // Supported subset of Meta categories: authentication requires a separate OTP form.
        'categories' => ['UTILITY' => 'Utilitaire', 'MARKETING' => 'Marketing'],
        'languages' => ['fr' => 'Français', 'en_US' => 'Anglais (États-Unis)', 'en' => 'Anglais'],
        'queue' => 'whatsapp-templates',
        'sync_enabled' => env('WHATSAPP_TEMPLATE_SYNC_ENABLED', false),
        'max_status_age_minutes' => 60,
    ],
    'override' => env('WHATSAPP_TEST_OVERRIDE'),
];
