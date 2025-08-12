<?php

namespace Artryazanov\ArtisanTranslator\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use SplFileInfo;

class TranslationFileService
{
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
        $key = $this->generateKey($bladeFile, $string); // without root prefix
        $langFilePath = $this->getLangFilePath($bladeFile);

        $translations = $this->loadTranslations($langFilePath);

        $keyParts = explode('.', $key);
        $leafKey = array_pop($keyParts);

        $currentLevel = &$translations;
        foreach ($keyParts as $part) {
            // Ignore the lang root path if ever present in key parts (safety)
            if ($part === $this->langRootPath) {
                continue;
            }
            if (! isset($currentLevel[$part]) || ! is_array($currentLevel[$part])) {
                $currentLevel[$part] = [];
            }
            $currentLevel = &$currentLevel[$part];
        }

        if (! isset($currentLevel[$leafKey]) || $force) {
            $currentLevel[$leafKey] = $string;
            $this->saveTranslations($langFilePath, $translations);
        }

        return $this->langRootPath.'.'.implode('.', $keyParts).'.'.$leafKey;
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

        $content = "<?php\n\nreturn ".var_export($translations, true).";\n";
        $this->filesystem->put($path, $content);
    }
}
