<?php

namespace Artryazanov\ArtisanTranslator\Commands;

use Artryazanov\ArtisanTranslator\Contracts\TranslationService;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Artryazanov\ArtisanTranslator\Concerns\ExportsShortArrays;

class TranslateStringsCommand extends Command
{
    use ExportsShortArrays;

    protected $signature = 'translate:ai'
        .' {source? : Source language (defaults to config)}'
        .' {--targets=* : Target languages (e.g., --targets=de --targets=fr)}'
        .' {--force : Overwrite existing translations}';

    protected $description = 'Translates strings to other languages using the Gemini API.';

    public function handle(Filesystem $filesystem, TranslationService $translator): int
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

        // Configurable minimal interval between AI requests (in seconds)
        $delaySeconds = (float) config('artisan-translator.ai_request_delay_seconds', 2.0);
        $previousRequestDuration = null; // seconds

        foreach ($sourceFiles as $sourceFile) {
            $sourceData = $filesystem->getRequire($sourceFile->getRealPath());
            if (! is_array($sourceData)) {
                $sourceData = [];
            }
            $translations = Arr::dot($sourceData);

            foreach ($targetLangs as $targetLang) {
                $this->line("➤ Translating to '{$targetLang}' for file: ".$sourceFile->getRelativePathname());

                $targetFilePath = lang_path("{$targetLang}/{$langRootPath}/".$sourceFile->getRelativePathname());
                $existingTranslations = [];
                if ($filesystem->exists($targetFilePath)) {
                    $existingData = $filesystem->getRequire($targetFilePath);
                    $existingTranslations = is_array($existingData) ? Arr::dot($existingData) : [];
                }

                $newTranslations = [];
                foreach ($translations as $key => $text) {
                    if ($text === '' || $text === null) {
                        continue;
                    }
                    if (! $force && isset($existingTranslations[$key])) {
                        continue; // already translated
                    }

                    // Respect delay between AI requests by accounting for previous request duration
                    if ($previousRequestDuration !== null && $delaySeconds > 0) {
                        $sleepFor = $delaySeconds - $previousRequestDuration;
                        if ($sleepFor > 0) {
                            // Convert seconds to microseconds for finer granularity
                            usleep((int) round($sleepFor * 1_000_000));
                        }
                    }

                    $this->comment("  - Translating key '{$key}'...");
                    $fullKey = $langRootPath.'.'.$key;
                    $context = [
                        'key' => $fullKey,
                        'file' => $sourceFile->getRelativePathname(),
                    ];

                    $start = microtime(true);
                    try {
                        $translatedText = $translator->translate((string) $text, $sourceLang, $targetLang, $context);

                        // Strip wrapping double quotes from translated text unless the source was also wrapped
                        $sourceWrapped = $this->isWrappedWithDoubleQuotes((string) $text);
                        if (! $sourceWrapped) {
                            $translatedText = $this->unwrapOuterDoubleQuotes($translatedText);
                        }

                        $newTranslations[$key] = $translatedText;
                        $totalStringsTranslated++;
                    } catch (\Throwable $e) {
                        $this->error("    Translation error: {$e->getMessage()}");
                    } finally {
                        // Measure only the AI request duration for the next-iteration delay
                        $previousRequestDuration = max(0.0, microtime(true) - $start);
                    }
                }

                if (! empty($newTranslations)) {
                    $this->saveTranslations($targetFilePath, $newTranslations, $existingTranslations);
                }
            }
        }

        $this->info("✅ Done. Total strings translated: {$totalStringsTranslated}.");

        return self::SUCCESS;
    }

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

    /**
     * Check if a string is wrapped with ASCII double quotes (") after trimming whitespace.
     */
    private function isWrappedWithDoubleQuotes(string $value): bool
    {
        $trimmed = trim($value);

        return strlen($trimmed) >= 2 && $trimmed[0] === '"' && str_ends_with($trimmed, '"');
    }

    /**
     * Remove a single pair of outer ASCII double quotes (") if present, preserving inner content.
     * Does not strip any surrounding whitespace other than what is inside the captured quotes.
     */
    private function unwrapOuterDoubleQuotes(string $value): string
    {
        $trimmed = trim($value);
        if (strlen($trimmed) >= 2 && $trimmed[0] === '"' && str_ends_with($trimmed, '"')) {
            $trimmed = substr($trimmed, 1, -1);
        }

        return $trimmed;
    }

    private function saveTranslations(string $path, array $new, array $existing): void
    {
        $all = array_merge($existing, $new);
        $undotted = [];
        foreach ($all as $key => $value) {
            Arr::set($undotted, $key, $value);
        }

        $directory = dirname($path);
        $fs = app(Filesystem::class);
        if (! $fs->isDirectory($directory)) {
            $fs->makeDirectory($directory, 0755, true, true);
        }

        $export = $this->varExportShort($undotted);
        $content = "<?php\n\nreturn " . $export . ";\n";
        $fs->put($path, $content);
    }

}
