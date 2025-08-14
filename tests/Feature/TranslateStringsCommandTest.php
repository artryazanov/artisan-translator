<?php

namespace Artryazanov\ArtisanTranslator\Tests\Feature;

use Artryazanov\ArtisanTranslator\Contracts\TranslationService;
use Artryazanov\ArtisanTranslator\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Mockery;

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
                ->with('Hello', 'en', 'de', Mockery::on(function ($ctx) {
                    return is_array($ctx) && isset($ctx['key']);
                }))
                ->andReturn('Hallo');
            $mock->shouldReceive('translate')
                ->with('World', 'en', 'de', Mockery::on(function ($ctx) {
                    return is_array($ctx) && isset($ctx['key']);
                }))
                ->andReturn('Welt');
        });

        // Run command
        $this->artisan('translate:ai', ['source' => 'en', '--targets' => ['de']])
            ->assertSuccessful();

        // Assert target file
        $targetLangPath = lang_path('de/blade/test.php');
        $this->assertTrue(File::exists($targetLangPath));

        // Content should use short array syntax
        $content = File::get($targetLangPath);
        $this->assertStringContainsString('return [', $content);
        $this->assertStringNotContainsString('array (', $content);

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

    public function test_strips_outer_double_quotes_when_source_not_quoted(): void
    {
        // Source has unquoted text
        $sourceLangPath = lang_path('en/blade/quotes.php');
        File::makeDirectory(dirname($sourceLangPath), 0755, true, true);
        File::put($sourceLangPath, "<?php return [\n    'title' => 'Model Gemini',\n];\n");

        // Mock TranslationService to return text wrapped in quotes
        $this->mock(TranslationService::class, function ($mock) {
            $mock->shouldReceive('translate')
                ->with('Model Gemini', 'en', 'ru', Mockery::on(fn ($ctx) => is_array($ctx) && isset($ctx['key'])))
                ->andReturn('"Модель Gemini"');
        });

        // Run command
        $this->artisan('translate:ai', ['source' => 'en', '--targets' => ['ru']])
            ->assertSuccessful();

        // The saved translation should be without the outer quotes
        $targetLangPath = lang_path('ru/blade/quotes.php');
        $this->assertTrue(File::exists($targetLangPath));
        $translations = require $targetLangPath;
        $this->assertSame('Модель Gemini', $translations['title'] ?? null);
    }

    public function test_preserves_outer_double_quotes_when_source_is_quoted(): void
    {
        // Source has quoted text
        $sourceLangPath = lang_path('en/blade/quotes2.php');
        File::makeDirectory(dirname($sourceLangPath), 0755, true, true);
        File::put($sourceLangPath, "<?php return [\n    'label' => '\"Output Tokens\"',\n];\n");

        // Mock TranslationService to return text wrapped in quotes as well
        $this->mock(TranslationService::class, function ($mock) {
            $mock->shouldReceive('translate')
                ->with('"Output Tokens"', 'en', 'ru', Mockery::on(fn ($ctx) => is_array($ctx) && isset($ctx['key'])))
                ->andReturn('"Токены вывода"');
        });

        // Run command
        $this->artisan('translate:ai', ['source' => 'en', '--targets' => ['ru']])
            ->assertSuccessful();

        // The saved translation should keep the outer quotes
        $targetLangPath = lang_path('ru/blade/quotes2.php');
        $this->assertTrue(File::exists($targetLangPath));
        $translations = require $targetLangPath;
        $this->assertSame('"Токены вывода"', $translations['label'] ?? null);
    }
}
