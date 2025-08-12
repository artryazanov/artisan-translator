<?php

return [
    // ISO 639-1 code of the source language used in Blade literals
    'source_language' => env('ARTISAN_TRANSLATOR_SOURCE_LANG', 'en'),

    // Root subdirectory under resources/lang/{locale} to store generated files
    'lang_root_path' => env('ARTISAN_TRANSLATOR_LANG_ROOT', 'blade'),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-pro'),
    ],

    // If true and package mcamara/laravel-localization is installed,
    // translate:ai can auto-detect target languages
    'mcamara_localization_support' => true,
];
