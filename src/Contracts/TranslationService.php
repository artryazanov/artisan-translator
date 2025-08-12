<?php

namespace Artryazanov\ArtisanTranslator\Contracts;

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
}
