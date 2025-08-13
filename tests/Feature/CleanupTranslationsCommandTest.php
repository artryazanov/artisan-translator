<?php

namespace Artryazanov\ArtisanTranslator\Tests\Feature;

use Artryazanov\ArtisanTranslator\Tests\TestCase;
use Illuminate\Support\Facades\File;

class CleanupTranslationsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Arrange language and views structure
        File::makeDirectory(lang_path('en'), 0755, true, true);
        File::makeDirectory(resource_path('views'), 0755, true, true);

        // PHP group file with one used and one unused key
        File::put(lang_path('en/messages.php'), "<?php return ['used' => 'Used', 'unused' => 'Unused'];");

        // JSON with one used and one unused key
        File::put(lang_path('en.json'), json_encode([
            'json_used' => 'JSON Used',
            'json_unused' => 'JSON Unused',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Blade that references the used keys only
        File::put(resource_path('views/welcome.blade.php'), "{{ __('messages.used') }} {{ __('json_used') }}");
    }

    public function dry_run_identifies_unused_keys_but_does_not_delete_them(): void
    {
        $this->artisan('translations:cleanup', ['--dry-run' => true])
            ->expectsOutputToContain('Found 2 unused translation keys:')
            ->expectsOutputToContain('messages.unused')
            ->expectsOutputToContain('json_unused')
            ->expectsOutput('Dry-run mode is enabled. No files were changed.')
            ->assertSuccessful();

        $this->assertStringContainsString("'unused' => 'Unused'", File::get(lang_path('en/messages.php')));
        $this->assertStringContainsString('"json_unused": "JSON Unused"', File::get(lang_path('en.json')));
    }

    public function command_prompts_for_confirmation_and_aborts_on_no(): void
    {
        $this->artisan('translations:cleanup')
            ->expectsConfirmation('Do you want to delete these keys? This action cannot be undone.', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertFailed();

        $this->assertStringContainsString("'unused' => 'Unused'", File::get(lang_path('en/messages.php')));
    }

    public function command_removes_unused_keys_on_confirmation(): void
    {
        $this->artisan('translations:cleanup')
            ->expectsConfirmation('Do you want to delete these keys? This action cannot be undone.', 'yes')
            ->assertSuccessful();

        $content = File::get(lang_path('en/messages.php'));
        $this->assertStringNotContainsString("'unused' => 'Unused'", $content);
        $this->assertStringContainsString("'used' => 'Used'", $content);
    }

    public function command_deletes_empty_php_language_file_after_cleanup(): void
    {
        // A file with only one unused key -> becomes empty -> should be deleted
        File::put(lang_path('en/lonely.php'), "<?php return ['orphan' => 'Orphan'];");

        $this->artisan('translations:cleanup', ['--force' => true])
            ->expectsOutputToContain('The following files were deleted because they became empty:')
            ->expectsOutputToContain(lang_path('en/lonely.php'))
            ->assertSuccessful();

        $this->assertFileDoesNotExist(lang_path('en/lonely.php'));
    }

    public function force_option_skips_confirmation_and_deletes_keys(): void
    {
        $this->artisan('translations:cleanup', ['--force' => true])
            ->doesntExpectOutput('Do you want to delete these keys?')
            ->expectsOutput('Cleanup completed.')
            ->assertSuccessful();

        $this->assertStringNotContainsString("'unused' => 'Unused'", File::get(lang_path('en/messages.php')));
    }
}
