<?php

namespace Artryazanov\ArtisanTranslator\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Artryazanov\ArtisanTranslator\ArtisanTranslatorServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ArtisanTranslatorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Temporary directories for tests
        $app['path.lang'] = __DIR__ . '/temp/lang';
        $app->instance('path.resources', __DIR__ . '/temp/resources');
        if (method_exists($app, 'useLangPath')) {
            $app->useLangPath(__DIR__ . '/temp/lang');
        }
        if (method_exists($app, 'useResourcePath')) {
            $app->useResourcePath(__DIR__ . '/temp/resources');
        }

        // Ensure config defaults
        $app['config']->set('artisan-translator.source_language', 'en');
        $app['config']->set('artisan-translator.lang_root_path', 'blade');
        $app['config']->set('artisan-translator.mcamara_localization_support', false);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->recursivelyDeleteDirectory(__DIR__ . '/temp');
        @mkdir(__DIR__ . '/temp/resources/views', 0777, true);
        @mkdir(__DIR__ . '/temp/lang', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->recursivelyDeleteDirectory(__DIR__ . '/temp');
        parent::tearDown();
    }

    private function recursivelyDeleteDirectory(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }
        $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }
        @rmdir($dir);
    }
}
