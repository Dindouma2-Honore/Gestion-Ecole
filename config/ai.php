<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Fournisseur d'IA par défaut
    |--------------------------------------------------------------------------
    | Options supportées: "openai", "gemini", "local"
    | Si aucune clé API n'est configurée, le système bascule élégamment en mode "local"
    */
    'provider' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Clé API IA générale et modèle
    |--------------------------------------------------------------------------
    */
    'api_key' => env('AI_API_KEY'),
    'model' => env('AI_MODEL', 'gpt-4o-mini'),
    'sync' => (bool) env('AI_SYNC_DISPATCH', true),

    /*
    |--------------------------------------------------------------------------
    | Configuration OpenAI
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'api_key' => env('OPENAI_API_KEY', env('AI_API_KEY')),
        'model' => env('OPENAI_MODEL', env('AI_MODEL', 'gpt-4o-mini')),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration Google Gemini
    |--------------------------------------------------------------------------
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', env('AI_API_KEY')),
        'model' => env('GEMINI_MODEL', env('AI_MODEL', 'gemini-1.5-flash')),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Confidentialité et gestion du texte
    |--------------------------------------------------------------------------
    */
    'save_extracted_text' => (bool) env('AI_SAVE_EXTRACTED_TEXT', true),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 4000),
    'temperature' => (float) env('AI_TEMPERATURE', 0.2),
];
