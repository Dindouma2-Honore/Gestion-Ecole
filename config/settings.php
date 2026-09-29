<?php

use App\Modules\Socle\Settings\SystemSettings;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;

return [
    'settings' => [SystemSettings::class],
    'setting_class_path' => app_path('Modules/Socle/Settings'),
    'migrations_paths' => [app_path('Modules/Socle/database/settings')],
    'default_repository' => 'database',
    'repositories' => [
        'database' => [
            'type' => DatabaseSettingsRepository::class,
            'model' => null,
            'table' => 'settings',
            'connection' => null,
        ],
    ],
    'encoder' => null,
    'decoder' => null,
    'cache' => [
        'enabled' => (bool) env('SETTINGS_CACHE_ENABLED', false),
        'store' => null,
        'prefix' => null,
        'ttl' => null,
        'memo' => false,
    ],
    'global_casts' => [],
    'auto_discover_settings' => [],
    'discovered_settings_cache_path' => base_path('bootstrap/cache'),
];
