<?php

declare(strict_types=1);

use Artryazanov\ArtisanTranslator\Services\TranslationFileService;
use Artryazanov\ArtisanTranslator\Services\TranslationRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;

it('saves string and generates expected key and file', function () {
    // Arrange: create a dummy blade file path
    $bladePath = resource_path('views/pages/home.blade.php');
    File::makeDirectory(dirname($bladePath), 0755, true, true);
    File::put($bladePath, '<h1>Test</h1>');

    $filesystem = new Filesystem;
    $repository = new TranslationRepository($filesystem);
    $service = new TranslationFileService($repository);

    $bladeFile = new SplFileInfo($bladePath);
    $key = $service->saveString($bladeFile, 'Explore Videos', false);

    // The returned key should include root path with slash-separated group
    expect($key)->toBe('blade/pages/home.explore_videos');

    $langFilePath = lang_path('en/blade/pages/home.php');
    expect(File::exists($langFilePath))->toBeTrue();

    // Assert content uses short array syntax
    $content = File::get($langFilePath);
    expect($content)
        ->toContain('return [')
        ->not->toContain('array (');

    $translations = require $langFilePath;
    expect($translations['explore_videos'] ?? null)->toBe('Explore Videos');
});

it('accumulates multiple strings in one run', function () {
    // Arrange: create a dummy blade file path under nested folders
    $bladePath = resource_path('views/components/layouts/footer.blade.php');
    File::makeDirectory(dirname($bladePath), 0755, true, true);
    File::put($bladePath, '<footer>Footer</footer>');

    $repository = new TranslationRepository(new Filesystem);
    $service = new TranslationFileService($repository);
    $bladeFile = new SplFileInfo($bladePath);

    // Act: save multiple strings sequentially to the same translation file
    $k1 = $service->saveString($bladeFile, 'Your portal to retro-futuristic gaming', false);
    $k2 = $service->saveString($bladeFile, 'All rights reserved.', false);
    $k3 = $service->saveString($bladeFile, 'TheGamerBay Gaming Portal.', false);

    // Assert: returned keys point to the same group
    expect($k1)->toStartWith('blade/components/layouts/footer.')
        ->and($k2)->toStartWith('blade/components/layouts/footer.')
        ->and($k3)->toStartWith('blade/components/layouts/footer.');

    $langFilePath = lang_path('en/blade/components/layouts/footer.php');
    expect(File::exists($langFilePath))->toBeTrue();

    $translations = require $langFilePath;

    // Extract leaf keys from returned full keys
    $leaf1 = substr($k1, strrpos($k1, '.') + 1);
    $leaf2 = substr($k2, strrpos($k2, '.') + 1);
    $leaf3 = substr($k3, strrpos($k3, '.') + 1);

    expect($translations[$leaf1] ?? null)->toBe('Your portal to retro-futuristic gaming')
        ->and($translations[$leaf2] ?? null)->toBe('All rights reserved.')
        ->and($translations[$leaf3] ?? null)->toBe('TheGamerBay Gaming Portal.');
});
