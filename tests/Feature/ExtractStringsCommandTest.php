<?php

namespace Artryazanov\ArtisanTranslator\Tests\Feature;

use Artryazanov\ArtisanTranslator\Tests\TestCase;
use Illuminate\Support\Facades\File;

class ExtractStringsCommandTest extends TestCase
{
    public function test_extract_command_works_correctly(): void
    {
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
        $this->assertTrue(File::exists($langPath));

        $translations = require $langPath;
        $this->assertEquals('Welcome', $translations['welcome'] ?? null);
        $this->assertEquals('This is a test.', $translations['this_is_a_test'] ?? null);

        // Assert blade updated
        $updatedBladeContent = File::get($bladePath);
        $this->assertStringContainsString("__('blade/pages/home.welcome')", $updatedBladeContent);
        $this->assertStringContainsString("__('blade/pages/home.this_is_a_test')", $updatedBladeContent);
    }

    public function test_dry_run_option_prevents_file_changes(): void
    {
        $bladeContent = "<h1>{{ __('Dry Run Test') }}</h1>";
        $bladePath = resource_path('views/dry.blade.php');
        File::makeDirectory(dirname($bladePath), 0755, true, true);
        File::put($bladePath, $bladeContent);

        $this->artisan('translate:extract', ['--dry-run' => true])
            ->expectsOutput('Dry-run mode is enabled. No changes will be saved.')
            ->assertSuccessful();

        $this->assertFalse(File::exists(lang_path('en/blade/dry.php')));
        $this->assertEquals($bladeContent, File::get($bladePath));
    }
}
