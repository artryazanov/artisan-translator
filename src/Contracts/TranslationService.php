<?php

namespace Artryazanov\ArtisanTranslator\Contracts;

/**
 * Contract for a translation service provider.
 */
interface TranslationService
{
    /**
     * Translate a text from one language to another.
     *
     * @param  string  $sourceLang  ISO 639-1 code of source language
     * @param  string  $targetLang  ISO 639-1 code of target language
     * @param  array  $context  Additional context like ['key' => '...', 'file' => '...']
     */
    public function translate(string $text, string $sourceLang, string $targetLang, array $context = []): string;

    /**
     * Translate a batch of strings.
     *
     * @param  array<string, string>  $strings  Map of key => text
     * @return array<string, string> Map of key => translated_text
     */
    public function translateBatch(array $strings, string $sourceLang, string $targetLang, array $context = []): array;
}
