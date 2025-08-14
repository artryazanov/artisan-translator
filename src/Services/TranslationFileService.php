<?php

namespace Artryazanov\ArtisanTranslator\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Artryazanov\ArtisanTranslator\Concerns\ExportsShortArrays;
use SplFileInfo;

class TranslationFileService
{
    use ExportsShortArrays;

    private Filesystem $filesystem;

    private string $langRootPath;

    private string $sourceLanguage;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->langRootPath = (string) config('artisan-translator.lang_root_path', 'blade');
        $this->sourceLanguage = (string) config('artisan-translator.source_language', 'en');
    }

    /**
     * Save a string to the corresponding translation file and return the generated key path (with root prefix).
     */
    public function saveString(SplFileInfo $bladeFile, string $string, bool $force): ?string
    {
        // Determine blade-relative path (without extension) to build group path with slashes
        $viewsBase = str_replace('\\', '/', rtrim(resource_path('views'), '\\/')).'/';
        $absPath = str_replace('\\', '/', $bladeFile->getPathname());
        $relativePath = Str::after($absPath, $viewsBase);
        $pathWithoutExtension = str_replace('.blade.php', '', $relativePath);

        // Build leaf key from string
        $snake = (string) Str::of($string)->lower()->snake();
        $snake = (string) preg_replace('/[^a-z0-9_]+/', '', $snake);
        $snake = trim($snake, '_');
        $leafKey = (string) Str::of($snake)->limit(50, '');

        $langFilePath = $this->getLangFilePath($bladeFile);
        $translations = $this->loadTranslations($langFilePath);

        // Write as a flat array inside the file (no redundant directory nesting)
        if (! isset($translations[$leafKey]) || $force) {
            $translations[$leafKey] = $string;
            $this->saveTranslations($langFilePath, $translations);
        }

        // Return a key that uses slash-separated group for subfolders as Laravel expects
        return $this->langRootPath.'/'.$pathWithoutExtension.'.'.$leafKey;
    }

    /**
     * Generate key based on blade path and snake-cased text (without root prefix).
     */
    private function generateKey(SplFileInfo $bladeFile, string $string): string
    {
        // Normalize paths to forward slashes to be OS-agnostic
        $viewsBase = str_replace('\\', '/', rtrim(resource_path('views'), '\\/')).'/';
        $absPath = str_replace('\\', '/', $bladeFile->getPathname());
        $relativePath = Str::after($absPath, $viewsBase);

        $pathWithoutExtension = str_replace('.blade.php', '', $relativePath);
        $pathParts = preg_split('/[\\\\\/]+/', $pathWithoutExtension, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Build a clean snake-cased key from text: remove any non [a-z0-9_] characters (like dots)
        $snake = (string) Str::of($string)->lower()->snake();
        $snake = (string) preg_replace('/[^a-z0-9_]+/', '', $snake);
        $snake = trim($snake, '_');
        $stringKey = (string) Str::of($snake)->limit(50, '');

        $keyParts = array_merge($pathParts, [$stringKey]);

        return implode('.', $keyParts);
    }

    /**
     * Determine lang file path for the given Blade file.
     */
    private function getLangFilePath(SplFileInfo $bladeFile): string
    {
        // Normalize paths to forward slashes to be OS-agnostic
        $viewsBase = str_replace('\\', '/', rtrim(resource_path('views'), '\\/')).'/';
        $absPath = str_replace('\\', '/', $bladeFile->getPathname());
        $relativePath = Str::after($absPath, $viewsBase);

        $pathWithoutExtension = str_replace('.blade.php', '', $relativePath);

        return lang_path("{$this->sourceLanguage}/{$this->langRootPath}/{$pathWithoutExtension}.php");
    }

    /**
     * Load existing translations or return empty array.
     */
    private function loadTranslations(string $path): array
    {
        if ($this->filesystem->exists($path)) {
            $data = $this->filesystem->getRequire($path);

            return is_array($data) ? $data : [];
        }

        return [];
    }

    /**
     * Save translations array to PHP file.
     */
    private function saveTranslations(string $path, array $translations): void
    {
        $directory = dirname($path);
        if (! $this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }

        $export = $this->varExportShort($translations);
        $content = "<?php\n\nreturn " . $export . ";\n";
        $this->filesystem->put($path, $content);
    }

}
