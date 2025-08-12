<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scan Paths
    |--------------------------------------------------------------------------
    | Directories to scan for translation key usages. You can add more paths
    | like directories for Livewire, Vue, etc.
    */
    'scan_paths' => [
        app_path(),
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Extensions
    |--------------------------------------------------------------------------
    | File extensions or glob-like patterns to scan. Simple extensions like
    | 'php' are normalized internally to '*.php'.
    */
    'file_extensions' => [
        'php',
        'blade.php',
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation Functions / Directives
    |--------------------------------------------------------------------------
    | A list of helper functions and Blade directives used in your codebase to
    | fetch translations. Include both helpers (e.g. __, trans, trans_choice)
    | and directives (prefixed with @, e.g. @lang, @choice) as needed.
    */
    'translation_functions' => [
        '__',
        'trans',
        'trans_choice',
        '@lang',
        '@choice',
    ],
];
