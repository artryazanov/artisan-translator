<?php

namespace Artryazanov\ArtisanTranslator\Commands;

use Artryazanov\ArtisanTranslator\Services\TranslationCleanerService;
use Illuminate\Console\Command;

class CleanupTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:cleanup'
        .' {--dry-run : Show unused keys without deleting them}'
        .' {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove unused translation keys and delete empty language files.';

    /**
     * Handle the console command. The command acts as an orchestrator and delegates business logic
     * to TranslationCleanerService, following SRP.
     */
    public function handle(TranslationCleanerService $cleaner): int
    {
        $this->info('Scanning for unused translation keys...');

        $langPaths = [lang_path()];
        $scanPaths = config('translation-cleaner.scan_paths', [app_path(), resource_path('views')]);

        $unusedKeys = $cleaner->findUnusedKeys($scanPaths, $langPaths);

        if (empty($unusedKeys)) {
            $this->info('No unused translation keys found.');

            return self::SUCCESS;
        }

        $this->warn('Found '.count($unusedKeys).' unused translation keys:');
        foreach ($unusedKeys as $k) {
            $this->line(' - '.$k);
        }

        if ($this->option('dry-run')) {
            $this->info('Dry-run mode is enabled. No files were changed.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Do you want to delete these keys? This action cannot be undone.')) {
                $this->info('Operation cancelled.');

                return self::FAILURE;
            }
        }

        $report = $cleaner->removeUnusedKeys($unusedKeys, $langPaths);

        $this->info('Cleanup completed.');
        $this->info('Removed keys: '.count($report['removed']));
        if (! empty($report['deleted_files'])) {
            $this->warn('The following files were deleted because they became empty:');
            foreach ($report['deleted_files'] as $file) {
                $this->line(' - '.$file);
            }
        }

        return self::SUCCESS;
    }
}
