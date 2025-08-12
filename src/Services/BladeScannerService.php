<?php

namespace Artryazanov\ArtisanTranslator\Services;

use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;

class BladeScannerService
{
    /**
     * Find all Blade files in resources/views or its subdirectory.
     *
     * @param  string|null  $subPath  Optional subdirectory within resources/views
     * @return Collection Collection of SplFileInfo (Symfony Finder)
     */
    public function find(?string $subPath): Collection
    {
        $viewPath = resource_path('views');
        $scanPath = $subPath ? $viewPath.DIRECTORY_SEPARATOR.$subPath : $viewPath;

        if (! is_dir($scanPath)) {
            return new Collection([]);
        }

        $finder = new Finder;
        $files = $finder->in($scanPath)->files()->name('*.blade.php');

        return new Collection(iterator_to_array($files));
    }
}
