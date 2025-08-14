<?php

namespace Artryazanov\ArtisanTranslator\Tests\Unit;

use Artryazanov\ArtisanTranslator\Services\TranslationFileService;
use Artryazanov\ArtisanTranslator\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use SplFileInfo;

class TranslationFileServiceMultipleSavesTest extends TestCase
{
    public function test_it_accumulates_multiple_strings_in_one_run(): void
    {
        // Arrange: create a dummy blade file path under nested folders
        $bladePath = resource_path('views/components/layouts/footer.blade.php');
        File::makeDirectory(dirname($bladePath), 0755, true, true);
        File::put($bladePath, '<footer>Footer</footer>');

        $service = new TranslationFileService(new Filesystem);
        $bladeFile = new SplFileInfo($bladePath);

        // Act: save multiple strings sequentially to the same translation file
        $k1 = $service->saveString($bladeFile, 'Your portal to retro-futuristic gaming', false);
        $k2 = $service->saveString($bladeFile, 'All rights reserved.', false);
        $k3 = $service->saveString($bladeFile, 'TheGamerBay Gaming Portal.', false);

        // Assert: returned keys point to the same group
        $this->assertStringStartsWith('blade/components/layouts/footer.', $k1);
        $this->assertStringStartsWith('blade/components/layouts/footer.', $k2);
        $this->assertStringStartsWith('blade/components/layouts/footer.', $k3);

        $langFilePath = lang_path('en/blade/components/layouts/footer.php');
        $this->assertTrue(File::exists($langFilePath));

        $translations = require $langFilePath;

        // Extract leaf keys from returned full keys
        $leaf1 = substr($k1, strrpos($k1, '.') + 1);
        $leaf2 = substr($k2, strrpos($k2, '.') + 1);
        $leaf3 = substr($k3, strrpos($k3, '.') + 1);

        $this->assertSame('Your portal to retro-futuristic gaming', $translations[$leaf1] ?? null);
        $this->assertSame('All rights reserved.', $translations[$leaf2] ?? null);
        $this->assertSame('TheGamerBay Gaming Portal.', $translations[$leaf3] ?? null);
    }
}
