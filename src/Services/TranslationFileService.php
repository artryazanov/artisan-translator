<?php

declare(strict_types=1);

namespace Artryazanov\ArtisanTranslator\Services;

use Artryazanov\ArtisanTranslator\Concerns\ExportsShortArrays;
use Illuminate\Support\Str;
use SplFileInfo;

/**
 * Service responsible for managing translation files, including saving new strings.
 */
class TranslationFileService
{
    use ExportsShortArrays;

    private string $langRootPath;

    private string $sourceLanguage;

    public function __construct(
        private readonly TranslationRepository $repository
    ) {
        $this->langRootPath = (string) config('artisan-translator.lang_root_path', 'blade');
        $this->sourceLanguage = (string) config('artisan-translator.source_language', 'en');
    }

    /**
     * Save a string to the corresponding translation file and return the generated key path (with root prefix).
     *
     * @param  SplFileInfo  $bladeFile  Source blade file
     * @param  string  $string  Text to translate/save
     * @param  bool  $force  Overwrite existing key
     * @return string|null The generated translation key (e.g. "blade/dir/file.key")
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
        $translations = $this->repository->load($langFilePath);

        // Write as a flat array inside the file (no redundant directory nesting)
        if (! isset($translations[$leafKey]) || $force) {
            $translations[$leafKey] = $string;
            $this->repository->save($langFilePath, $translations);
        }

        // Return a key that uses slash-separated group for subfolders as Laravel expects
        return $this->langRootPath.'/'.$pathWithoutExtension.'.'.$leafKey;
    }

    /**
     * Determine lang file path for the given Blade file.
     *
     * @return string Absolute path to language file
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
}
