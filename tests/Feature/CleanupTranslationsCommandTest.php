<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
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
});

it('identifies unused keys but does not delete them in dry-run mode', function () {
    $this->artisan('translations:cleanup', ['--dry-run' => true])
        ->expectsOutputToContain('Found 2 unused translation keys:')
        ->expectsOutputToContain('messages.unused')
        ->expectsOutputToContain('json_unused')
        ->expectsOutput('Dry-run mode is enabled. No files were changed.')
        ->assertSuccessful();

    expect(File::get(lang_path('en/messages.php')))->toContain("'unused' => 'Unused'");
    expect(File::get(lang_path('en.json')))->toContain('"json_unused": "JSON Unused"');
});

it('prompts for confirmation and aborts on no', function () {
    $this->artisan('translations:cleanup')
        ->expectsConfirmation('Do you want to delete these keys? This action cannot be undone.', 'no')
        ->expectsOutput('Operation cancelled.')
        ->assertFailed();

    expect(File::get(lang_path('en/messages.php')))->toContain("'unused' => 'Unused'");
});

it('removes unused keys on confirmation', function () {
    $this->artisan('translations:cleanup')
        ->expectsConfirmation('Do you want to delete these keys? This action cannot be undone.', 'yes')
        ->assertSuccessful();

    $content = File::get(lang_path('en/messages.php'));
    expect($content)
        ->not->toContain("'unused' => 'Unused'")
        ->toContain("'used' => 'Used'");
});

it('deletes empty php language file after cleanup', function () {
    // A file with only one unused key -> becomes empty -> should be deleted
    File::put(lang_path('en/lonely.php'), "<?php return ['orphan' => 'Orphan'];");

    $this->artisan('translations:cleanup', ['--force' => true])
        ->expectsOutputToContain('The following files were deleted because they became empty:')
        ->expectsOutputToContain(lang_path('en/lonely.php'))
        ->assertSuccessful();

    expect(File::exists(lang_path('en/lonely.php')))->toBeFalse();
});

it('skips confirmation and deletes keys with force option', function () {
    $this->artisan('translations:cleanup', ['--force' => true])
        ->doesntExpectOutput('Do you want to delete these keys?')
        ->expectsOutput('Cleanup completed.')
        ->assertSuccessful();

    expect(File::get(lang_path('en/messages.php')))->not->toContain("'unused' => 'Unused'");
});
