<?php

namespace Artryazanov\ArtisanTranslator\Commands;

use Artryazanov\ArtisanTranslator\Services\BladeScannerService;
use Artryazanov\ArtisanTranslator\Services\BladeWriterService;
use Artryazanov\ArtisanTranslator\Services\StringExtractorService;
use Artryazanov\ArtisanTranslator\Services\TranslationFileService;
use Illuminate\Console\Command;

class ExtractStringsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translate:extract '
        .'{--path= : Limit scanning to the specified subdirectory within resources/views} '
        .'{--dry-run : Perform without writing changes to files} '
        .'{--force : Overwrite existing keys in translation files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extracts translatable strings from Blade files and replaces them with keys.';

    public function handle(
        BladeScannerService $scanner,
        StringExtractorService $extractor,
        TranslationFileService $fileService,
        BladeWriterService $writer
    ): int {
        $this->info('🚀 Starting extraction of translatable strings...');

        $path = $this->option('path');
        $isDryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($isDryRun) {
            $this->warn('Dry-run mode is enabled. No changes will be saved.');
        }

        $bladeFiles = $scanner->find($path);
        if ($bladeFiles->isEmpty()) {
            $this->warn('No Blade files found. Nothing to process.');

            return self::SUCCESS;
        }

        $this->getOutput()->progressStart($bladeFiles->count());
        $totalStringsExtracted = 0;

        foreach ($bladeFiles as $file) {
            $strings = $extractor->extract($file->getRealPath());

            if (empty($strings)) {
                $this->getOutput()->progressAdvance();

                continue;
            }

            $replacements = [];
            foreach ($strings as $string) {
                if (! $isDryRun) {
                    $key = $fileService->saveString($file, $string, $force);
                    if ($key) {
                        $replacements[$string] = $key;
                    }
                }
                $totalStringsExtracted++;
            }

            if (! empty($replacements) && ! $isDryRun) {
                $writer->updateBladeFile($file->getRealPath(), $replacements);
            }

            $this->getOutput()->progressAdvance();
        }

        $this->getOutput()->progressFinish();
        $this->info("✅ Done. Total strings extracted: {$totalStringsExtracted}.");

        return self::SUCCESS;
    }
}
