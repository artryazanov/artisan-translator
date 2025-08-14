<?php

namespace Artryazanov\ArtisanTranslator\Services;

class StringExtractorService
{
    /**
     * Extract strings wrapped in __() or @lang() from a Blade file.
     * Ignores already externalized keys like 'file.key' (alphanumeric, dashes/underscores with dots),
     * but keeps normal sentences even if they contain punctuation dots.
     */
    public function extract(string $filePath): array
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        // Two patterns: one for single quoted, one for double quoted
        $patternSingle = "/(?:__|@lang)\(\s*'((?:\\\\.|[^'\\\\])*)'/u";
        $patternDouble = '/(?:__|@lang)\(\s*"((?:\\\\.|[^"\\\\])*)"/u';

        $matches = [];
        if (preg_match_all($patternSingle, $content, $m1)) {
            $matches = array_merge($matches, $m1[1]);
        }
        if (preg_match_all($patternDouble, $content, $m2)) {
            $matches = array_merge($matches, $m2[1]);
        }
        if (empty($matches)) {
            return [];
        }

        $strings = [];
        foreach ($matches as $text) {
            $original = stripcslashes($text);
            $trimmed = trim($original);
            if ($trimmed === '' || $this->isLikelyTranslationKey($trimmed)) {
                continue;
            }
            // Preserve original whitespace so that BladeWriter can match exact literal in the file
            $strings[] = $original;
        }

        return array_values(array_unique($strings));
    }

    private function isLikelyTranslationKey(string $value): bool
    {
        // Accept keys like:
        // - group.with.dots (classic file-based groups)
        // - group/sub/group.leaf (subfolder groups with slash)
        // Segments are [A-Za-z0-9_-]
        return (bool) preg_match('/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*\.[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/', $value);
    }
}
