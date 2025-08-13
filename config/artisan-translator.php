<?php

use Artryazanov\ArtisanTranslator\Enums\GeminiModel;

return [
    /*
    |--------------------------------------------------------------------------
    | Source Language
    |--------------------------------------------------------------------------
    | ISO 639-1 code of the source language used in Blade literals.
    | This determines from which locale the AI translation will read.
    */
    'source_language' => env('ARTISAN_TRANSLATOR_SOURCE_LANG', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Language Root Path
    |--------------------------------------------------------------------------
    | Root subdirectory under resources/lang/{locale} to store generated files.
    | For example, with 'blade' and source 'en', files go under:
    | resources/lang/en/blade/...
    */
    'lang_root_path' => env('ARTISAN_TRANSLATOR_LANG_ROOT', 'blade'),

    /*
    |--------------------------------------------------------------------------
    | AI Request Delay
    |--------------------------------------------------------------------------
    | Minimal interval between consecutive AI requests in seconds. The actual
    | sleep before the next request is calculated as:
    |   max(0, ai_request_delay_seconds - previous_request_duration)
    | where previous_request_duration is only the time spent waiting for the
    | AI to respond (not including any other processing).
    */
    'ai_request_delay_seconds' => (float) env('ARTISAN_TRANSLATOR_AI_DELAY', 2.0),

    /*
    |--------------------------------------------------------------------------
    | Gemini Settings
    |--------------------------------------------------------------------------
    | Credentials and model for google-gemini-php/laravel integration.
    | Set GEMINI_API_KEY in your .env. Model defaults to GeminiModel::GEMMA_3_27B_IT.
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', GeminiModel::GEMMA_3_27B_IT->value),
    ],

    /*
    |--------------------------------------------------------------------------
    | mcamara/laravel-localization Integration
    |--------------------------------------------------------------------------
    | When true and the package is installed, translate:ai can auto-detect
    | target languages from LaravelLocalization::getSupportedLocales().
    */
    'mcamara_localization_support' => true,
];
