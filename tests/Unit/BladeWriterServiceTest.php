<?php

declare(strict_types=1);

use Artryazanov\ArtisanTranslator\Services\BladeWriterService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;

it('updates blade file protecting parameters in calls', function () {
    $bladePath = resource_path('views/pages/with_params.blade.php');
    File::makeDirectory(dirname($bladePath), 0755, true, true);

    $original = 'Search results for ":search"';
    $content = <<<'BLADE'
<div>
    {{ __('Search results for ":search"', ['search' => $search]) }}
    @lang('Search results for ":search"', ['search' => $search])
</div>
BLADE;
    File::put($bladePath, $content);

    $service = new BladeWriterService(new Filesystem);
    $service->updateBladeFile($bladePath, [
        $original => 'blade/pages/with_params.search_results_for_search',
    ]);

    $updated = File::get($bladePath);
    expect($updated)
        ->toContain("__('blade/pages/with_params.search_results_for_search', ['search' => \$search])")
        ->toContain("__('blade/pages/with_params.search_results_for_search', ['search' => \$search])");
});
