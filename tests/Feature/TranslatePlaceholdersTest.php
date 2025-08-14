<?php

namespace Artryazanov\ArtisanTranslator\Tests\Feature;

use Artryazanov\ArtisanTranslator\Contracts\TranslationService;
use Artryazanov\ArtisanTranslator\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Mockery;

class TranslatePlaceholdersTest extends TestCase
{
    public function test_ai_translation_preserves_laravel_placeholders(): void
    {
        // Prepare source translation file with placeholder
        $sourceLangPath = lang_path('en/blade/params.php');
        File::makeDirectory(dirname($sourceLangPath), 0755, true, true);
        $sourceContent = "<?php return [\n    'search_line' => 'Search results for \"::search\"',\n];\n";
        // Note: we intentionally include a double colon to avoid PHP escaping issues in this fixture, then fix it below
        $sourceContent = str_replace('::search', ':search', $sourceContent);
        File::put($sourceLangPath, $sourceContent);

        // Mock TranslationService
        $this->mock(TranslationService::class, function ($mock) {
            $expectedMasked = 'Search results for "[[[PLH1]]]"';
            $mock->shouldReceive('translate')
                ->with($expectedMasked, 'en', 'ru', Mockery::on(fn ($ctx) => is_array($ctx) && isset($ctx['key'])))
                ->andReturn('Результаты поиска для "[[[PLH1]]]"');
        });

        // Run command
        $this->artisan('translate:ai', ['source' => 'en', '--targets' => ['ru']])
            ->assertSuccessful();

        // Assert target file
        $targetLangPath = lang_path('ru/blade/params.php');
        $this->assertTrue(File::exists($targetLangPath));
        $translations = require $targetLangPath;
        $this->assertSame('Результаты поиска для ":search"', $translations['search_line'] ?? null);
    }
}
