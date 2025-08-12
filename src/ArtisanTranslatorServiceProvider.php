<?php

namespace Artryazanov\ArtisanTranslator;

use Artryazanov\ArtisanTranslator\Commands\ExtractStringsCommand;
use Artryazanov\ArtisanTranslator\Commands\TranslateStringsCommand;
use Artryazanov\ArtisanTranslator\Contracts\TranslationService;
use Artryazanov\ArtisanTranslator\Services\GeminiTranslationService;
use Illuminate\Support\ServiceProvider;

class ArtisanTranslatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/artisan-translator.php', 'artisan-translator');

        $this->app->singleton(TranslationService::class, function ($app) {
            return new GeminiTranslationService(
                $app['config']['artisan-translator.gemini.api_key'] ?? '',
                $app['config']['artisan-translator.gemini.model'] ?? 'gemini-2.5-pro'
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExtractStringsCommand::class,
                TranslateStringsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/artisan-translator.php' => config_path('artisan-translator.php'),
            ], 'config');
        }
    }
}
