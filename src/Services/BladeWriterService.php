<?php

declare(strict_types=1);

namespace Artryazanov\ArtisanTranslator\Services;

use Illuminate\Filesystem\Filesystem;

/**
 * Service responsible for modifying Blade files to replace static strings with translation keys.
 */
class BladeWriterService
{
    public function __construct(
        private readonly Filesystem $filesystem
    ) {}

    /**
     * Update a Blade file replacing literal strings with translation keys.
     *
     * @param  array<string,string>  $replacements  map ['Original Text' => 'blade.path.key']
     */
    public function updateBladeFile(string $filePath, array $replacements): void
    {
        $content = $this->filesystem->get($filePath);

        foreach ($replacements as $string => $key) {
            $rxSingle = preg_quote(addcslashes($string, "\\'"), '/');   // ' -> \', \ -> \\
            $rxDouble = preg_quote(addcslashes($string, '\\"$'), '/');  // " -> \", \ -> \\, $ -> \$

            $patterns = [
                "/__\(\s*'{$rxSingle}'\s*(,\s*[^)]*)?\)/u",
                "/__\(\s*\"{$rxDouble}\"\s*(,\s*[^)]*)?\)/u",
                "/@lang\(\s*'{$rxSingle}'\s*(,\s*[^)]*)?\)/u",
                "/@lang\(\s*\"{$rxDouble}\"\s*(,\s*[^)]*)?\)/u",
            ];

            // Keep optional parameter tail via backreference $1
            $replacementString = "__('".$key."'\$1)";
            $content = preg_replace($patterns, $replacementString, $content);
        }

        $this->filesystem->put($filePath, $content);
    }
}
