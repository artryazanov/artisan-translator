<?php

namespace Artryazanov\ArtisanTranslator\Tests\Unit;

use Artryazanov\ArtisanTranslator\Services\BladeWriterService;
use Artryazanov\ArtisanTranslator\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;

class BladeWriterServiceTest extends TestCase
{
    public function test_update_blade_file_preserves_parameters_in_calls(): void
    {
        $bladePath = resource_path('views/pages/with_params.blade.php');
        File::makeDirectory(dirname($bladePath), 0755, true, true);

        $original = "Search results for \":search\"";
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
        $this->assertStringContainsString('__(\'blade/pages/with_params.search_results_for_search\', [\'search\' => $search])', $updated);
        $this->assertStringContainsString('__(\'blade/pages/with_params.search_results_for_search\', [\'search\' => $search])', $updated, 'Also replaces @lang with __ while preserving params');
    }
}
