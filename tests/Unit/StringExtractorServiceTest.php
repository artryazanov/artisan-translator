<?php

declare(strict_types=1);

use Artryazanov\ArtisanTranslator\Services\StringExtractorService;

// Helper to create temp file
function createTempBladeFile(string $content): string
{
    $path = __DIR__.'/../temp/test.blade.php';
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $content);

    return $path;
}

beforeEach(function () {
    $this->extractor = new StringExtractorService;
});

it('extracts strings from double quotes', function () {
    $content = '<h1>{{ __("Hello World") }}</h1>';
    $path = createTempBladeFile($content);

    $result = $this->extractor->extract($path);

    expect($result)->toBe(['Hello World']);
});

it('extracts strings from single quotes', function () {
    $content = "<div>{{ __('Explore Videos') }}</div>";
    $path = createTempBladeFile($content);

    $result = $this->extractor->extract($path);

    expect($result)->toBe(['Explore Videos']);
});

it('ignores strings with dots as keys', function () {
    $content = "<p>{{ __('user.profile.title') }}</p>";
    $path = createTempBladeFile($content);

    $result = $this->extractor->extract($path);

    expect($result)->toBeEmpty();
});

it('extracts from lang directive', function () {
    $content = "<span>@lang('Submit Button')</span>";
    $path = createTempBladeFile($content);

    $result = $this->extractor->extract($path);

    expect($result)->toBe(['Submit Button']);
});

it('handles multiple strings and returns unique', function () {
    $content = "\n            <title>{{ __('Page Title') }}</title>\n            <h1>{{ __('Page Title') }}</h1>\n            <p>{{ __('Some other text.') }}</p>\n        ";
    $path = createTempBladeFile($content);

    $result = $this->extractor->extract($path);

    expect($result)
        ->toHaveCount(2)
        ->toContain('Page Title')
        ->toContain('Some other text.');
});
