<?php

declare(strict_types=1);

namespace Artryazanov\ArtisanTranslator\Services;

use Artryazanov\ArtisanTranslator\Contracts\TranslationService;
use Artryazanov\ArtisanTranslator\Exceptions\TranslationServiceException;
use Gemini\Laravel\Facades\Gemini as GeminiFacade;

/**
 * Implementation of TranslationService using Google Gemini API.
 */
class GeminiTranslationService implements TranslationService
{
    /**
     * @param  string  $apiKey  Gemini API Key
     * @param  string  $model  Gemini Model Name (e.g. "gemini-1.5-flash")
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model
    ) {}

    /**
     * Translate a single text string.
     *
     * @param  string  $text  Text to translate
     * @param  string  $sourceLang  Source language code (ISO 639-1)
     * @param  string  $targetLang  Target language code (ISO 639-1)
     * @param  array  $context  Additional context (key, file, etc.)
     * @return string Translated text
     *
     * @throws TranslationServiceException
     */
    public function translate(string $text, string $sourceLang, string $targetLang, array $context = []): string
    {
        $prompt = $this->buildPrompt($text, $sourceLang, $targetLang, $context);

        try {
            $result = GeminiFacade::generativeModel(model: $this->model)->generateContent($prompt);
            $out = $result->text();

            return trim((string) $out);
        } catch (\Throwable $e) {
            throw new TranslationServiceException('Gemini API error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Build the prompt for single string translation.
     */
    private function buildPrompt(string $text, string $sourceLang, string $targetLang, array $context): string
    {
        $lines = [];
        $lines[] = "Translate the following text from '{$sourceLang}' to '{$targetLang}'.";
        $lines[] = 'The text is part of a Laravel web application UI.';
        if (! empty($context['key'])) {
            $lines[] = "Translation key: {$context['key']}";
        }
        if (! empty($context['file'])) {
            $lines[] = "Source file: {$context['file']}";
        }
        $lines[] = 'Preserve any HTML tags and placeholders like :variable.';
        $lines[] = 'Return only the translated text with no extra commentary.';
        $lines[] = '';
        $lines[] = 'Text:';
        $lines[] = '"'.$text.'"';

        return implode("\n", $lines);
    }

    /**
     * Translate a batch of strings.
     *
     * @param  array<string, string>  $strings  Map of key => text
     * @return array<string, string> Map of key => translated_text
     */
    public function translateBatch(array $strings, string $sourceLang, string $targetLang, array $context = []): array
    {
        if (empty($strings)) {
            return [];
        }

        $prompt = $this->buildBatchPrompt($strings, $sourceLang, $targetLang, $context);

        return $this->retry(function () use ($prompt, $strings) {
            $result = GeminiFacade::generativeModel(model: $this->model)
                ->generateContent($prompt);

            $json = $this->extractJson($result->text());

            // Validate keys exist
            $translated = [];
            foreach ($strings as $key => $original) {
                if (isset($json[$key])) {
                    $translated[$key] = (string) $json[$key];
                } else {
                    // Key missing in JSON response.
                    // We skip it in the result; the caller will treat it as a failure for this key.
                }
            }

            return $translated;
        });
    }

    /**
     * Build the prompt for batch translation.
     */
    private function buildBatchPrompt(array $strings, string $sourceLang, string $targetLang, array $context): string
    {
        $json = json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $lines = [];
        $lines[] = "Translate the values of the following JSON object from '{$sourceLang}' to '{$targetLang}'.";
        $lines[] = 'The text is part of a Laravel web application UI.';
        $lines[] = 'Preserve keys exactly as is.';
        $lines[] = 'Preserve any HTML tags and placeholders like :variable.';
        $lines[] = 'Output ONLY valid JSON object with the same keys and translated values.';
        $lines[] = '';
        $lines[] = 'Input JSON:';
        $lines[] = $json;

        return implode("\n", $lines);
    }

    /**
     * Extract and parse JSON from the model response.
     * Handles markdown code blocks if present.
     *
     * @param  string  $text  Raw response text
     * @return array Parsed JSON data
     */
    private function extractJson(string $text): array
    {
        // Try to find JSON object in text (Gemini might add markdown ```json ... ```)
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $text, $matches)) {
            $text = $matches[1];
        } elseif (preg_match('/(\{.*\})/s', $text, $matches)) {
            $text = $matches[1];
        }

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new \RuntimeException('Failed to parse JSON response: '.$text);
        }

        return $decoded;
    }

    /**
     * Retry an action with exponential backoff.
     *
     * @param  callable  $action  Function to retry
     * @param  int  $attempts  Max attempts
     * @param  int  $sleepMs  Initial sleep time in milliseconds
     * @return mixed Result of action
     *
     * @throws TranslationServiceException
     */
    private function retry(callable $action, int $attempts = 3, int $sleepMs = 1000)
    {
        $lastException = null;
        for ($i = 0; $i < $attempts; $i++) {
            try {
                return $action();
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($i < $attempts - 1) {
                    usleep($sleepMs * 1000);
                    $sleepMs *= 2; // Exponential backoff
                }
            }
        }
        throw new TranslationServiceException('Operation failed after retries: '.$lastException->getMessage(), 0, $lastException);
    }
}
