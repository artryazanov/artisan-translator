<?php

namespace Artryazanov\ArtisanTranslator\Concerns;

trait ExportsShortArrays
{
    /**
     * Export arrays using short syntax [] instead of array().
     * Also normalize indentation to PSR-12 (4 spaces).
     */
    /**
     * Export arrays using short syntax [] instead of array().
     * Also normalize indentation to PSR-12 (4 spaces).
     *
     * @param mixed $expression
     * @return string
     */
    protected function varExportShort(mixed $expression): string
    {
        $export = var_export($expression, true);
        $export = preg_replace(['/(^|\b)array\s*\(/', '/\)(?=\s*(,|\)|$))/'], ['[', ']'], $export);

        if (! is_string($export)) {
            $export = var_export($expression, true);
            $export = preg_replace(['/(^|\b)array\s*\(/', '/\)(?=\s*(,|\)|$))/'], ['[', ']'], $export);
        }

        // Normalize indentation: var_export uses 2 spaces per level; convert to 4 spaces per level (PSR-12).
        $export = preg_replace_callback('/^ +/m', static function (array $m) {
            $spaces = strlen($m[0]);
            $levels = intdiv($spaces, 2);

            return str_repeat('    ', $levels);
        }, $export);

        return $export;
    }
}
