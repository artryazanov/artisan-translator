<?php

declare(strict_types=1);

use Artryazanov\ArtisanTranslator\Contracts\TranslationService;
use Illuminate\Support\Facades\File;

it('works correctly', function () {
    // Prepare source translation file
    $sourceLangPath = lang_path('en/blade/test.php');
    File::makeDirectory(dirname($sourceLangPath), 0755, true, true);
    $sourceContent = "<?php return [\n    'hello' => 'Hello',\n    'world' => 'World',\n];\n";
    File::put($sourceLangPath, $sourceContent);

    // Mock TranslationService
    $this->mock(TranslationService::class, function ($mock) {
        $mock->shouldReceive('translateBatch')
            ->with(Mockery::on(function ($batch) {
                return isset($batch['hello']) && $batch['hello'] === 'Hello'
                    && isset($batch['world']) && $batch['world'] === 'World';
            }), 'en', 'de', Mockery::on(function ($ctx) {
                return is_array($ctx) && isset($ctx['file']);
            }))
            ->andReturn(['hello' => 'Hallo', 'world' => 'Welt']);
    });

    // Run command
    $this->artisan('translate:ai', ['source' => 'en', '--targets' => ['de']])
        ->assertSuccessful();

    // Assert target file
    $targetLangPath = lang_path('de/blade/test.php');
    expect(File::exists($targetLangPath))->toBeTrue();

    // Content should use short array syntax
    $content = File::get($targetLangPath);
    expect($content)
        ->toContain('return [')
        ->not->toContain('array (');

    $translations = require $targetLangPath;
    expect($translations['hello'] ?? null)->toBe('Hallo')
        ->and($translations['world'] ?? null)->toBe('Welt');
});

it('fails if no targets are provided or detected', function () {
    config(['artisan-translator.mcamara_localization_support' => false]);

    $this->artisan('translate:ai', ['source' => 'en'])
        ->expectsOutput('Target languages are not provided and cannot be determined automatically.')
        ->assertFailed();
});

it('strips outer double quotes when source is not quoted', function () {
    $string = 'Model Gemini';
    $translation = '"Модель Gemini"';
    $expectedStored = 'Модель Gemini';

    $sourceLangPath = lang_path('en/blade/quotes_unquoted.php');
    File::makeDirectory(dirname($sourceLangPath), 0755, true, true);
    // Use var_export for safe PHP code generation
    $phpContent = "<?php return [\n    'key' => ".var_export($string, true).",\n];\n";
    File::put($sourceLangPath, $phpContent);

    // Mock
    $this->mock(TranslationService::class, function ($mock) use ($translation, $string) {
        $mock->shouldReceive('translateBatch')
            ->with(Mockery::on(function ($batch) use ($string) {
                return isset($batch['key']) && $batch['key'] === $string;
            }), 'en', 'ru', Mockery::any())
            ->andReturn(['key' => $translation]);
    });

    $this->artisan('translate:ai', ['source' => 'en', '--targets' => ['ru']])
        ->assertSuccessful();

    $targetLangPath = lang_path('ru/blade/quotes_unquoted.php');
    expect(File::exists($targetLangPath))->toBeTrue();
    $translations = require $targetLangPath;
    expect($translations['key'] ?? null)->toBe($expectedStored);
});

it('preserves outer double quotes when source is quoted', function () {
    $string = '"Output Tokens"';
    $translation = '"Токены вывода"';
    $expectedStored = '"Токены вывода"';

    $sourceLangPath = lang_path('en/blade/quotes_quoted.php');
    File::makeDirectory(dirname($sourceLangPath), 0755, true, true);
    $phpContent = "<?php return [\n    'key' => ".var_export($string, true).",\n];\n";
    File::put($sourceLangPath, $phpContent);

    // Mock
    $this->mock(TranslationService::class, function ($mock) use ($translation, $string) {
        $mock->shouldReceive('translateBatch')
            ->with(Mockery::on(function ($batch) use ($string) {
                // $batch['key'] should match the source string exactly (including quotes)
                return isset($batch['key']) && $batch['key'] === $string;
            }), 'en', 'ru', Mockery::any())
            ->andReturn(['key' => $translation]);
    });

    $this->artisan('translate:ai', ['source' => 'en', '--targets' => ['ru']])
        ->assertSuccessful();

    $targetLangPath = lang_path('ru/blade/quotes_quoted.php');
    expect(File::exists($targetLangPath))->toBeTrue();
    $translations = require $targetLangPath;
    expect($translations['key'] ?? null)->toBe($expectedStored);
});
