<?php

namespace Artryazanov\ArtisanTranslator\Tests\Feature;

use Illuminate\Support\Facades\File;
use Mockery;
use Artryazanov\ArtisanTranslator\Contracts\TranslationService;
use Artryazanov\ArtisanTranslator\Tests\TestCase;

class TranslateStringsCommandTest extends TestCase
{
    public function test_translate_command_works_correctly(): void
    {
        // Prepare source translation file
        $sourceLangPath = lang_path('en/blade/test.php');
        File::makeDirectory(dirname($sourceLangPath), 0755, true, true);
        $sourceContent = "<?php return [\n    'hello' => 'Hello',\n    'world' => 'World',\n];\n";
        File::put($sourceLangPath, $sourceContent);

        // Mock TranslationService
        $this->mock(TranslationService::class, function ($mock) {
            $mock->shouldReceive('translate')
                 ->with('Hello', 'en', 'de', Mockery::on(function ($ctx) { return is_array($ctx) && isset($ctx['key']); }))
                 ->andReturn('Hallo');
            $mock->shouldReceive('translate')
                 ->with('World', 'en', 'de', Mockery::on(function ($ctx) { return is_array($ctx) && isset($ctx['key']); }))
                 ->andReturn('Welt');
        });

        // Run command
        $this->artisan('translate:ai', ['source' => 'en', '--targets' => ['de']])
            ->assertSuccessful();

        // Assert target file
        $targetLangPath = lang_path('de/blade/test.php');
        $this->assertTrue(File::exists($targetLangPath));
        $translations = require $targetLangPath;
        $this->assertEquals('Hallo', $translations['hello'] ?? null);
        $this->assertEquals('Welt', $translations['world'] ?? null);
    }

    public function test_command_fails_if_no_targets_are_provided_or_detected(): void
    {
        config(['artisan-translator.mcamara_localization_support' => false]);

        $this->artisan('translate:ai', ['source' => 'en'])
            ->expectsOutput('Target languages are not provided and cannot be determined automatically.')
            ->assertFailed();
    }
}
