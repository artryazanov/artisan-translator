<?php

namespace Artryazanov\ArtisanTranslator\Services;

use Illuminate\Filesystem\Filesystem;

class BladeWriterService
{
    private Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

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
                "/__\(\s*'{$rxSingle}'\s*\)/u",
                "/__\(\s*\"{$rxDouble}\"\s*\)/u",
                "/@lang\(\s*'{$rxSingle}'\s*\)/u",
                "/@lang\(\s*\"{$rxDouble}\"\s*\)/u",
            ];

            $replacementString = "__('".$key."')";
            $content = preg_replace($patterns, $replacementString, $content);
        }

        $this->filesystem->put($filePath, $content);
    }
}
