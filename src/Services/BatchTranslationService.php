<?php

declare(strict_types=1);

namespace Artryazanov\ArtisanTranslator\Services;

use Artryazanov\ArtisanTranslator\Contracts\TranslationService;
use Illuminate\Support\Arr;

/**
 * orchestrates the translation process for files, handling constraints like
 * batch size, API rate limits (delays), and progress reporting.
 */
class BatchTranslationService
{
    public function __construct(
        private readonly TranslationRepository $repository,
        private readonly TranslationService $translator,
        private readonly TranslationStringProcessor $processor,
        private readonly float $requestDelaySeconds = 0.0
    ) {}

    /**
     * Translate a single file from source language to target language.
     *
     * @param  callable|null  $onProgress  function(string $key, string $status): void
     * @return int Number of strings translated
     */
    public function translateFile(
        string $sourceFilePath,
        string $sourceLang,
        string $targetLang,
        string $targetFilePath,
        bool $force = false,
        ?callable $onProgress = null
    ): int {
        $sourceData = $this->repository->load($sourceFilePath);
        $translations = Arr::dot($sourceData);

        $existingTranslations = [];
        if ($this->repository->isDirectory(dirname($targetFilePath))) {
            $existingTranslations = Arr::dot($this->repository->load($targetFilePath));
        }

        $newTranslations = [];
        $count = 0;

        // Prepare pending items
        $pending = [];
        $batchSize = 20;

        foreach ($translations as $key => $text) {
            if ($text === '' || $text === null) {
                continue;
            }
            if (! $force && isset($existingTranslations[$key])) {
                continue;
            }

            $pending[$key] = (string) $text;

            if (count($pending) >= $batchSize) {
                $count += $this->processBatch($pending, $sourceLang, $targetLang, basename($sourceFilePath), $onProgress, $newTranslations);
                $pending = [];
            }
        }

        // Process remaining
        if (! empty($pending)) {
            $count += $this->processBatch($pending, $sourceLang, $targetLang, basename($sourceFilePath), $onProgress, $newTranslations);
        }

        if (! empty($newTranslations)) {
            $all = array_merge($existingTranslations, $newTranslations);
            $undotted = [];
            foreach ($all as $k => $v) {
                Arr::set($undotted, $k, $v);
            }

            $this->repository->save($targetFilePath, $undotted);
        }

        return $count;
    }

    /**
     * Process a batch of translations.
     * Mask placeholders -> Translate -> Unmask -> Validate -> Update Results.
     *
     * @param  array<string, string>  $batch
     * @param  array  $results  Reference to results array
     * @return int Number of successfully processed items
     */
    private function processBatch(
        array $batch,
        string $sourceLang,
        string $targetLang,
        string $filename,
        ?callable $onProgress,
        array &$results
    ): int {
        if (empty($batch)) {
            return 0;
        }

        // Pre-process: Masking
        $maskedBatch = [];
        $meta = [];

        foreach ($batch as $key => $text) {
            if ($onProgress) {
                $onProgress($key, 'translating');
            }
            [$masked, $phMap] = $this->processor->maskPlaceholders($text);
            $maskedBatch[$key] = $masked;
            $meta[$key] = [
                'phMap' => $phMap,
                'wrapped' => $this->processor->isWrappedWithDoubleQuotes($text),
                'original' => $text,
            ];
        }

        // Sleep if needed (simple delay between batches)
        if ($this->requestDelaySeconds > 0) {
            usleep((int) round($this->requestDelaySeconds * 1_000_000));
        }

        try {
            $translatedBatch = $this->translator->translateBatch($maskedBatch, $sourceLang, $targetLang, ['file' => $filename]);
        } catch (\Throwable $e) {
            // Report error for all keys in batch
            foreach ($batch as $key => $val) {
                if ($onProgress) {
                    $onProgress($key, 'error: '.$e->getMessage());
                }
            }

            return 0;
        }

        $successCount = 0;

        // Post-process
        foreach ($batch as $key => $originalText) {
            if (! isset($translatedBatch[$key])) {
                if ($onProgress) {
                    $onProgress($key, 'error: missing in response');
                }

                continue;
            }

            $translated = $translatedBatch[$key];
            $info = $meta[$key];

            // Unwrap if source was not wrapped (Gemini might add quotes even in JSON values context).
            if (! $info['wrapped']) {
                $translated = $this->processor->unwrapOuterDoubleQuotes($translated);
            }

            // Unmask
            $final = $this->processor->unmaskPlaceholders($translated, $info['phMap']);

            // Validate
            if (! $this->processor->validatePlaceholders($originalText, $final)) {
                if ($onProgress) {
                    $onProgress($key, 'warn: placeholder mismatch');
                }
                // Log warning but save the result.
            } else {
                if ($onProgress) {
                    $onProgress($key, 'done');
                }
            }

            $results[$key] = $final;
            $successCount++;
        }

        return $successCount;
    }
}
