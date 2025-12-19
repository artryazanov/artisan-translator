<?php

declare(strict_types=1);

use Artryazanov\ArtisanTranslator\Contracts\TranslationService;
use Illuminate\Support\Facades\File;

it('preserves laravel placeholders during ai translation', function () {
    // Prepare source translation file with placeholder
    $sourceLangPath = lang_path('en/blade/params.php');
    File::makeDirectory(dirname($sourceLangPath), 0755, true, true);
    $sourceContent = "<?php return [\n    'search_line' => 'Search results for \"::search\"',\n];\n";
    $sourceContent = str_replace('::search', ':search', $sourceContent);
    File::put($sourceLangPath, $sourceContent);

    // Mock TranslationService
    $this->mock(TranslationService::class, function ($mock) {
        $expectedMasked = 'Search results for "[[[PLH1]]]"';
        $mock->shouldReceive('translateBatch')
            ->with(
                Mockery::on(fn ($batch) => isset($batch['search_line']) && $batch['search_line'] === $expectedMasked),
                'en',
                'ru',
                Mockery::any()
            )
            ->andReturn(['search_line' => 'Результаты поиска для "[[[PLH1]]]"']);
    });

    // Run command
    $this->artisan('translate:ai', ['source' => 'en', '--targets' => ['ru']])
        ->assertSuccessful();

    // Assert target file
    $targetLangPath = lang_path('ru/blade/params.php');
    expect(File::exists($targetLangPath))->toBeTrue();

    $translations = require $targetLangPath;
    expect($translations['search_line'] ?? null)->toBe('Результаты поиска для ":search"');
});
