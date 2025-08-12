# Artisan Translator for Laravel 11/12

Artisan Translator is a Laravel package that helps you:
- extract translation strings from Blade templates like `__('...')` and `@lang('...')` (ignoring already externalized keys such as `file.key`),
- store them under `resources/lang/{locale}/{root}/...` (defaults: locale `en`, root `blade`),
- replace literals in Blade with generated keys (e.g., `__('blade.path.key')`),
- translate those files into other languages via the Gemini API (`google-gemini-php/laravel`).

## Installation (developing inside a monorepo)

1. Put the package into `packages/artryazanov/artisan-translator`.
2. Ensure Laravel auto-discovery picks up the service provider (defined in the package `composer.json`).
3. Optionally publish the config:

```bash
php artisan vendor:publish --provider="Artryazanov\ArtisanTranslator\ArtisanTranslatorServiceProvider" --tag="config"
```

Add your `GEMINI_API_KEY` to the host application's `.env` file.

## Configuration (config/artisan-translator.php)
- `source_language` — the source language of Blade literals (default: `en`).
- `lang_root_path` — the root folder under `resources/lang/{locale}` for generated files (default: `blade`).
- `gemini.api_key`, `gemini.model` — Gemini settings (default model: `gemini-2.5-pro`).
- `mcamara_localization_support` — if `true` and `mcamara/laravel-localization` is installed, target languages can be auto-detected.

## Commands

### translate:extract
Scans `resources/views` (or a subdirectory) and extracts strings.

Options:
- `--path=` limit scanning to a subdirectory of `resources/views`;
- `--dry-run` show what would change without writing files;
- `--force` overwrite existing keys in lang files.

### translate:ai
Translates strings found under `resources/lang/{source}/{root}` to other languages.

Arguments/options:
- `source` — source language (defaults to config value),
- `--targets=*` — list of target languages (can be specified multiple times),
- `--force` — overwrite existing translations.

If `--targets` is not provided and `mcamara_localization_support=true`, and `mcamara/laravel-localization` is installed, targets are taken from `LaravelLocalization::getSupportedLocales()`.

## Testing

This package ships with unit and feature tests (Pest + Orchestra Testbench):

```bash
composer test
```

## License
Unlicense. See LICENSE.md.
