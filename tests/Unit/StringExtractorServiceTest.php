<?php

namespace Artryazanov\ArtisanTranslator\Tests\Unit;

use Artryazanov\ArtisanTranslator\Services\StringExtractorService;
use Artryazanov\ArtisanTranslator\Tests\TestCase;

class StringExtractorServiceTest extends TestCase
{
    private StringExtractorService $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new StringExtractorService;
    }

    public function test_it_extracts_strings_from_double_quotes(): void
    {
        $content = '<h1>{{ __("Hello World") }}</h1>';
        $path = $this->createTempFile($content);

        $result = $this->extractor->extract($path);

        $this->assertEquals(['Hello World'], $result);
    }

    public function test_it_extracts_strings_from_single_quotes(): void
    {
        $content = "<div>{{ __('Explore Videos') }}</div>";
        $path = $this->createTempFile($content);

        $result = $this->extractor->extract($path);

        $this->assertEquals(['Explore Videos'], $result);
    }

    public function test_it_ignores_strings_with_dots_as_keys(): void
    {
        $content = "<p>{{ __('user.profile.title') }}</p>";
        $path = $this->createTempFile($content);

        $result = $this->extractor->extract($path);

        $this->assertEmpty($result);
    }

    public function test_it_extracts_from_lang_directive(): void
    {
        $content = "<span>@lang('Submit Button')</span>";
        $path = $this->createTempFile($content);

        $result = $this->extractor->extract($path);

        $this->assertEquals(['Submit Button'], $result);
    }

    public function test_it_handles_multiple_strings_and_returns_unique(): void
    {
        $content = "\n            <title>{{ __('Page Title') }}</title>\n            <h1>{{ __('Page Title') }}</h1>\n            <p>{{ __('Some other text.') }}</p>\n        ";
        $path = $this->createTempFile($content);

        $result = $this->extractor->extract($path);

        $this->assertCount(2, $result);
        $this->assertContains('Page Title', $result);
        $this->assertContains('Some other text.', $result);
    }

    private function createTempFile(string $content): string
    {
        $path = __DIR__.'/../temp/test.blade.php';
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $content);

        return $path;
    }
}
