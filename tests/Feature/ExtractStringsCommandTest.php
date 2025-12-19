<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('extracts strings correctly', function () {
    // Prepare: create a Blade file
    $bladeContent = "<h1>{{ __('Welcome') }}</h1><p>@lang('This is a test.')</p>";
    $bladePath = resource_path('views/pages/home.blade.php');
    File::makeDirectory(dirname($bladePath), 0755, true, true);
    File::put($bladePath, $bladeContent);

    // Run command
    $this->artisan('translate:extract')
        ->expectsOutput('🚀 Starting extraction of translatable strings...')
        ->assertSuccessful();

    // Assert translation file created
    $langPath = lang_path('en/blade/pages/home.php');
    expect(File::exists($langPath))->toBeTrue();

    $translations = require $langPath;
    expect($translations['welcome'] ?? null)->toBe('Welcome')
        ->and($translations['this_is_a_test'] ?? null)->toBe('This is a test.');

    // Assert blade updated
    $updatedBladeContent = File::get($bladePath);
    expect($updatedBladeContent)
        ->toContain("__('blade/pages/home.welcome')")
        ->toContain("__('blade/pages/home.this_is_a_test')");
});

it('prevents file changes in dry-run mode', function () {
    $bladeContent = "<h1>{{ __('Dry Run Test') }}</h1>";
    $bladePath = resource_path('views/dry.blade.php');
    File::makeDirectory(dirname($bladePath), 0755, true, true);
    File::put($bladePath, $bladeContent);

    $this->artisan('translate:extract', ['--dry-run' => true])
        ->expectsOutput('Dry-run mode is enabled. No changes will be saved.')
        ->assertSuccessful();

    expect(File::exists(lang_path('en/blade/dry.php')))->toBeFalse();
    expect(File::get($bladePath))->toBe($bladeContent);
});
