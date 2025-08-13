# Artisan Translator for Laravel 11/12

Artisan Translator is a Laravel package that helps you:
- extract translation strings from Blade templates like `__('...')` and `@lang('...')` (ignoring already externalized keys such as `file.key`),
- store them under `resources/lang/{locale}/{root}/...` (defaults: locale `en`, root `blade`),
- replace literals in Blade with generated keys (e.g., `__('blade.path.key')`),
- translate those files into other languages via the Gemini API (`google-gemini-php/laravel`).

## Installation

Install via Composer:

```bash
composer require artryazanov/artisan-translator
```

Laravel will auto-discover the service provider.

Optionally publish the config:

```bash
php artisan vendor:publish --provider="Artryazanov\ArtisanTranslator\ArtisanTranslatorServiceProvider" --tag="config"
```

Add your `GEMINI_API_KEY` to your application's `.env` file.

## Configuration (config/artisan-translator.php)
- `source_language` — the source language of Blade literals (default: `en`).
- `lang_root_path` — the root folder under `resources/lang/{locale}` for generated files (default: `blade`).
- `ai_request_delay_seconds` — minimal interval between consecutive AI requests in seconds (default: `2.0`). The actual sleep before the next request is `max(0, delay - previous_request_duration)`. You can override via ENV `ARTISAN_TRANSLATOR_AI_DELAY`.
- `gemini.api_key`, `gemini.model` — Gemini settings (default model: `gemma-3-27b-it`). Supported models via enum: `gemini-2.5-pro`, `gemini-2.5-flash`, `gemini-2.5-flash-lite`, `gemma-3-27b-it`. You can override via ENV `GEMINI_MODEL`.
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

Notes:
- The command respects the configured `ai_request_delay_seconds`, waiting `max(0, delay - previous_request_duration)` between AI requests.
- If `--targets` is not provided and `mcamara_localization_support=true`, and `mcamara/laravel-localization` is installed, targets are taken from `LaravelLocalization::getSupportedLocales()`.

### translations:cleanup
Finds translation keys that are defined in language files but not used in your app code, removes them, and deletes empty language files.

Options:
- `--dry-run` show unused keys without deleting anything;
- `--force` skip the confirmation prompt.

Configuration:
- Publish the config if needed:
  ```bash
  php artisan vendor:publish --provider="Artryazanov\ArtisanTranslator\ArtisanTranslatorServiceProvider" --tag="config"
  ```
- Configure scan paths, file extensions, and translation functions in `config/translation-cleaner.php`.

Safety note:
- This command is destructive. Always run with `--dry-run` first and ensure your project is under version control.

## Testing

This package ships with unit and feature tests (Pest + Orchestra Testbench):

```bash
composer test
```

## License
Unlicense. See LICENSE.md.
