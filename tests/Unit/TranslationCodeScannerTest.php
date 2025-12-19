<?php

declare(strict_types=1);

use Artryazanov\ArtisanTranslator\Services\TranslationCodeScanner;

function createTempCodeFile(string $content, string $ext = '.php'): string
{
    $dir = __DIR__.'/../temp/scan';
    @mkdir($dir, 0777, true);
    $path = $dir . '/test' . uniqid() . $ext;
    file_put_contents($path, $content);

    return $path;
}

beforeEach(function () {
    $this->scanner = new TranslationCodeScanner;
});

it('finds used translation keys in php files', function () {
    $file = createTempCodeFile("<?php echo __('messages.welcome'); echo trans('auth.failed');");
    
    $keys = $this->scanner->findUsedTranslationKeys([dirname($file)]);

    expect($keys)
        ->toContain('messages.welcome')
        ->toContain('auth.failed');
});

it('finds used translation keys in blade files', function () {
    $file = createTempCodeFile("@lang('messages.home') {{ __('messages.footer') }}", '.blade.php');
    
    $keys = $this->scanner->findUsedTranslationKeys([dirname($file)]);
    
    expect($keys)
        ->toContain('messages.home')
        ->toContain('messages.footer');
});

it('ignores unrelated functions', function () {
    $file = createTempCodeFile("<?php echo str_replace('a', 'b', 'c');");
    
    $keys = $this->scanner->findUsedTranslationKeys([dirname($file)]);
    
    expect($keys)->toBeEmpty();
});
