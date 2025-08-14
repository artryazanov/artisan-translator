<?php

namespace Artryazanov\ArtisanTranslator\Concerns;

trait ExportsShortArrays
{
    /**
     * Export arrays using short syntax [] instead of array().
     */
    protected function varExportShort(mixed $expression): string
    {
        $export = var_export($expression, true);
        $export = preg_replace(['/(^|\b)array\s*\(/', '/\)(?=\s*(,|\)|$))/'], ['[', ']'], $export);

        return is_string($export) ? $export : var_export($expression, true);
    }
}
