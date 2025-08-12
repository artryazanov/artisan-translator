<?php

namespace Artryazanov\ArtisanTranslator\Services;

use Artryazanov\ArtisanTranslator\Contracts\TranslationService;
use Artryazanov\ArtisanTranslator\Exceptions\TranslationServiceException;
use Gemini\Laravel\Facades\Gemini as GeminiFacade;

class GeminiTranslationService implements TranslationService
{
    private string $model;

    public function __construct(string $apiKey, string $model)
    {
        // API key is configured via env by the Gemini package; we keep constructor for API compatibility
        $this->model = $model;
    }

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
}
