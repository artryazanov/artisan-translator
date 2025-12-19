<?php

declare(strict_types=1);

namespace Artryazanov\ArtisanTranslator\Services;

use Artryazanov\ArtisanTranslator\Concerns\ExportsShortArrays;
use Illuminate\Filesystem\Filesystem;

/**
 * Repository responsible for reading and writing translation files.
 * Handles caching and file system abstraction.
 */
class TranslationRepository
{
    use ExportsShortArrays;

    /**
     * In-memory cache of translations per lang file path.
     *
     * @var array<string,array>
     */
    private array $translationsCache = [];

    public function __construct(
        private readonly Filesystem $filesystem
    ) {}

    /**
     * Load translations from a file.
     *
     * @return array<string, mixed>
     */
    public function load(string $path): array
    {
        if (array_key_exists($path, $this->translationsCache)) {
            return $this->translationsCache[$path];
        }

        if ($this->filesystem->exists($path)) {
            $data = $this->filesystem->getRequire($path);
            $translations = is_array($data) ? $data : [];

            return $this->translationsCache[$path] = $translations;
        }

        return $this->translationsCache[$path] = [];
    }

    /**
     * Save translations to a file.
     *
     * @param  array<string, mixed>  $translations
     */
    public function save(string $path, array $translations): void
    {
        $directory = dirname($path);
        if (! $this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }

        $this->translationsCache[$path] = $translations;

        $export = $this->varExportShort($translations);
        $content = "<?php\n\nreturn ".$export.";\n";
        $this->filesystem->put($path, $content);
    }

    /**
     * Load JSON translations.
     */
    public function loadJson(string $path): array
    {
        if (! $this->filesystem->exists($path)) {
            return [];
        }

        $content = $this->filesystem->get($path);
        if (! $content) {
            return [];
        }

        return json_decode($content, true) ?: [];
    }

    /**
     * Save JSON translations.
     */
    public function saveJson(string $path, array $translations): void
    {
        $this->filesystem->put($path, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
    }

    /**
     * Delete a file.
     */
    public function delete(string $path): bool
    {
        if (isset($this->translationsCache[$path])) {
            unset($this->translationsCache[$path]);
        }

        return $this->filesystem->delete($path);
    }

    /**
     * Check if a directory exists.
     */
    public function isDirectory(string $path): bool
    {
        return $this->filesystem->isDirectory($path);
    }
}
