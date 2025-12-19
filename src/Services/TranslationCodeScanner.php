<?php

declare(strict_types=1);

namespace Artryazanov\ArtisanTranslator\Services;

use Symfony\Component\Finder\Finder;

/**
 * Service responsible for scanning the codebase to find used translation keys.
 */
class TranslationCodeScanner
{
    /**
     * Scan project code for used translation keys.
     *
     * @param  array<string>  $scanPaths
     * @return array<string>
     */
    public function findUsedTranslationKeys(array $scanPaths): array
    {
        $keys = [];

        $functions = config('translation-cleaner.translation_functions', ['__', 'trans', 'trans_choice', '@lang', '@choice']);

        // Build patterns for helpers and blade directives
        $helperFns = array_filter($functions, fn ($f) => ! str_starts_with($f, '@'));
        $directives = array_filter($functions, fn ($f) => str_starts_with($f, '@'));

        $patterns = [];
        if (! empty($helperFns)) {
            $fnAlternation = implode('|', array_map(fn ($f) => preg_quote($f, '/'), $helperFns));
            // Match __("key") or trans('key') or trans_choice('key', ...)
            $patterns[] = '/(?:'.$fnAlternation.')\(\s*[\'"\"]([^\'"\"]+)[\'"\"]/u';
        }
        foreach ($directives as $d) {
            $name = ltrim($d, '@');
            // @lang('key') or @choice('key', ...)
            $patterns[] = '/@'.preg_quote($name, '/').'\(\s*[\'"\"]([^\'"\"]+)[\'"\"]/u';
        }

        $filePatterns = config('translation-cleaner.file_extensions', ['*.php', '*.blade.php']);
        $filePatterns = $this->normalizeFilePatterns($filePatterns);

        $finder = new Finder;
        $finder->in($scanPaths)->files()->name($filePatterns);

        foreach ($finder as $file) {
            $contents = $file->getContents();
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $contents, $m)) {
                    // capture group 1 contains the key
                    $keys = array_merge($keys, $m[1]);
                }
            }
        }

        // Normalize keys
        $keys = array_values(array_unique(array_map(static fn ($k) => trim(stripcslashes($k)), $keys)));
        sort($keys);

        return $keys;
    }

    /**
     * Normalize file patterns to ensure they match correctly.
     *
     * @param  array<string>  $patterns
     * @return array<string>
     */
    private function normalizeFilePatterns(array $patterns): array
    {
        return array_map(function ($p) {
            $p = trim($p);
            if ($p === '') {
                return $p;
            }
            if (str_starts_with($p, '*')) {
                return $p;
            }
            if (! str_contains($p, '.')) {
                return '*.'.$p;
            }

            return '*.'.ltrim($p, '*.');
        }, $patterns);
    }
}
