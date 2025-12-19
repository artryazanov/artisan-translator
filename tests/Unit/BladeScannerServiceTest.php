<?php

declare(strict_types=1);

use Artryazanov\ArtisanTranslator\Services\BladeScannerService;
use Illuminate\Support\Facades\File;

it('finds blade files in views directory', function () {
    // Arrange
    $path = resource_path('views/components/alert.blade.php');
    File::makeDirectory(dirname($path), 0755, true, true);
    File::put($path, 'test');

    $service = new BladeScannerService;
    $files = $service->find(null);

    // Filter to find our specific file, referencing basic filename
    $found = $files->filter(fn ($f) => $f->getFilename() === 'alert.blade.php');

    expect($found)->not->toBeEmpty();
});

it('finds blade files in subdirectory', function () {
    // Arrange
    $path = resource_path('views/admin/dashboard.blade.php');
    File::makeDirectory(dirname($path), 0755, true, true);
    File::put($path, 'test');

    $service = new BladeScannerService;
    $files = $service->find('admin');

    $found = $files->filter(fn ($f) => $f->getFilename() === 'dashboard.blade.php');

    expect($found)->not->toBeEmpty();
});
