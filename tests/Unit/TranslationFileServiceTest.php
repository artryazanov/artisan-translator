<?php

namespace Artryazanov\ArtisanTranslator\Tests\Unit;

use Artryazanov\ArtisanTranslator\Services\TranslationFileService;
use Artryazanov\ArtisanTranslator\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use SplFileInfo;

class TranslationFileServiceTest extends TestCase
{
    public function test_it_saves_string_and_generates_expected_key_and_file(): void
    {
        // Arrange: create a dummy blade file path
        $bladePath = resource_path('views/pages/home.blade.php');
        File::makeDirectory(dirname($bladePath), 0755, true, true);
        File::put($bladePath, '<h1>Test</h1>');

        $filesystem = new Filesystem;
        $service = new TranslationFileService($filesystem);

        $bladeFile = new SplFileInfo($bladePath);
        $key = $service->saveString($bladeFile, 'Explore Videos', false);

        // The returned key should include root path with slash-separated group
        $this->assertSame('blade/pages/home.explore_videos', $key);

        $langFilePath = lang_path('en/blade/pages/home.php');
        $this->assertTrue(File::exists($langFilePath));

        // Assert content uses short array syntax
        $content = File::get($langFilePath);
        $this->assertStringContainsString('return [', $content);
        $this->assertStringNotContainsString('array (', $content);

        $translations = require $langFilePath;
        $this->assertEquals('Explore Videos', $translations['explore_videos'] ?? null);
    }
}
