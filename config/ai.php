<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Módulo de Consultas en Lenguaje Natural (IA)
    |--------------------------------------------------------------------------
    |
    | Este módulo es completamente opcional y desacoplado del núcleo. Puede
    | desactivarse asignando AI_MODULE_ENABLED=false en el archivo .env.
    |
    */

    'enabled' => filter_var(env('AI_MODULE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'provider' => env('AI_PROVIDER', 'gemini'),

    'timeout' => (int) env('AI_TIMEOUT_SECONDS', 10),

    'providers' => [
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-flash-lite-latest'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
    ],
];
