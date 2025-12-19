<?php

declare(strict_types=1);

namespace Artryazanov\ArtisanTranslator\Commands;

use Artryazanov\ArtisanTranslator\Services\BatchTranslationService;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Command to translate translation files using AI.
 */
class TranslateStringsCommand extends Command
{
    protected $signature = 'translate:ai'
        .' {source? : Source language (defaults to config)}'
        .' {--targets=* : Target languages (e.g., --targets=de --targets=fr)}'
        .' {--force : Overwrite existing translations}';

    protected $description = 'Translates strings to other languages using the Gemini API.';

    public function handle(Filesystem $filesystem, BatchTranslationService $batchService): int
    {
        $this->info('🚀 Starting AI translation...');

        $sourceLang = $this->argument('source') ?? (string) config('artisan-translator.source_language', 'en');
        $targetLangs = $this->getTargetLanguages();
        $force = (bool) $this->option('force');

        if (empty($targetLangs)) {
            $this->error('Target languages are not provided and cannot be determined automatically.');
            $this->line('Please provide --targets or install mcamara/laravel-localization to auto-detect supported locales.');

            return self::FAILURE;
        }

        $this->info("Source language: {$sourceLang}");
        $this->info('Target languages: '.implode(', ', $targetLangs));

        $langRootPath = (string) config('artisan-translator.lang_root_path', 'blade');
        $sourcePath = lang_path("{$sourceLang}/{$langRootPath}");

        if (! $filesystem->isDirectory($sourcePath)) {
            $this->error("Source translations directory not found: {$sourcePath}");

            return self::FAILURE;
        }

        $sourceFiles = $filesystem->allFiles($sourcePath);
        $totalStringsTranslated = 0;

        foreach ($sourceFiles as $sourceFile) {
            foreach ($targetLangs as $targetLang) {
                if ($targetLang === $sourceLang) {
                    continue;
                }

                $this->line("➤ Translating to '{$targetLang}' for file: ".$sourceFile->getRelativePathname());

                $targetFilePath = lang_path("{$targetLang}/{$langRootPath}/".$sourceFile->getRelativePathname());

                $count = $batchService->translateFile(
                    $sourceFile->getRealPath(),
                    $sourceLang,
                    $targetLang,
                    $targetFilePath,
                    $force,
                    function (string $key, string $status) {
                        if ($status === 'translating') {
                            $this->comment("  - Translating key '{$key}'...");
                        } elseif (str_starts_with($status, 'error')) {
                            $this->error("    Translation error for '{$key}': ".substr($status, 7));
                        }
                    }
                );

                $totalStringsTranslated += $count;
            }
        }

        $this->info("✅ Done. Total strings translated: {$totalStringsTranslated}.");

        return self::SUCCESS;
    }

    /**
     * Determine target languages from options or config.
     *
     * @return array<string>
     */
    private function getTargetLanguages(): array
    {
        $targets = $this->option('targets');
        if (! empty($targets)) {
            return array_values(array_filter($targets));
        }

        if (config('artisan-translator.mcamara_localization_support')
            && class_exists(\Mcamara\LaravelLocalization\Facades\LaravelLocalization::class)) {
            $supportedLocales = \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getSupportedLocales();

            return array_keys($supportedLocales);
        }

        return [];
    }
}
