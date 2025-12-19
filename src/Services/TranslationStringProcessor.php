<?php

declare(strict_types=1);

namespace Artryazanov\ArtisanTranslator\Services;

/**
 * Helper service for string manipulation related to translations.
 * Handles quoting, placeholder masking/modification.
 */
class TranslationStringProcessor
{
    /**
     * Check if a string is wrapped with ASCII double quotes (") after trimming whitespace.
     */
    public function isWrappedWithDoubleQuotes(string $value): bool
    {
        $trimmed = trim($value);

        return strlen($trimmed) >= 2 && $trimmed[0] === '"' && str_ends_with($trimmed, '"');
    }

    /**
     * Remove a single pair of outer ASCII double quotes (") if present, preserving inner content.
     */
    public function unwrapOuterDoubleQuotes(string $value): string
    {
        $trimmed = trim($value);
        if (strlen($trimmed) >= 2 && $trimmed[0] === '"' && str_ends_with($trimmed, '"')) {
            $trimmed = substr($trimmed, 1, -1);
        }

        return $trimmed;
    }

    /**
     * Replace Laravel-style placeholders like :search with non-translatable tokens.
     * Returns [maskedText, map token=>originalPlaceholder].
     *
     * @return array{0: string, 1: array<string,string>}
     */
    public function maskPlaceholders(string $text): array
    {
        $i = 0;
        $map = [];
        $masked = preg_replace_callback('/:([A-Za-z_][A-Za-z0-9_]*)/', function ($m) use (&$i, &$map) {
            $i++;
            $token = '[[[PLH'.$i.']]]';
            $map[$token] = ':'.$m[1];

            return $token;
        }, $text);

        return [(string) $masked, $map];
    }

    /**
     * Restore masked tokens back to original placeholders.
     *
     * @param  array<string,string>  $map
     */
    public function unmaskPlaceholders(string $text, array $map): string
    {
        if (empty($map)) {
            return $text;
        }

        return strtr($text, $map);
    }

    /**
     * Validate that placeholders in the translated string match the source string.
     *
     * @return bool True if valid, False if placeholders are missing or mismatched
     */
    public function validatePlaceholders(string $source, string $translated): bool
    {
        $pattern = '/:[\w]+|{[^}]+}/';

        preg_match_all($pattern, $source, $sourceMatches);
        preg_match_all($pattern, $translated, $translatedMatches);

        $sourceCount = count($sourceMatches[0]);
        $translatedCount = count($translatedMatches[0]);

        if ($sourceCount !== $translatedCount) {
            return false;
        }

        // Check content regardless of order
        $s = $sourceMatches[0];
        $t = $translatedMatches[0];
        sort($s);
        sort($t);

        return $s === $t;
    }
}
