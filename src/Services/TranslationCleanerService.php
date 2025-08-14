<?php

namespace Artryazanov\ArtisanTranslator\Services;

use Artryazanov\ArtisanTranslator\Concerns\ExportsShortArrays;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * Service responsible for discovering defined translation keys, scanning code for used keys,
 * computing the difference, and removing unused keys from language files.
 *
 * All messages and comments are written in English as required.
 */
class TranslationCleanerService
{
    use ExportsShortArrays;

    public function __construct(private readonly Filesystem $files) {}

    /**
     * Find all unused translation keys given scan paths and language paths.
     *
     * @param  array<string>  $scanPaths  Directories to scan for usage.
     * @param  array<string>  $langPaths  Directories that contain language files (e.g. [lang_path()]).
     * @return array<string> List of unused keys.
     */
    public function findUnusedKeys(array $scanPaths, array $langPaths): array
    {
        $definedKeys = $this->getDefinedTranslationKeys($langPaths);
        $usedKeys = $this->findUsedTranslationKeys($scanPaths);

        // Compute keys that are defined but not used anywhere
        $unused = array_values(array_diff($definedKeys, $usedKeys));
        sort($unused);

        return $unused;
    }

    /**
     * Remove unused keys from language files and delete empty files.
     *
     * @param  array<string>  $unusedKeys  Keys to remove.
     * @param  array<string>  $langRoots  Language roots, typically [lang_path()].
     * @return array{removed: array<string>, deleted_files: array<string>} Report of removed keys and deleted files.
     */
    public function removeUnusedKeys(array $unusedKeys, array $langRoots): array
    {
        $report = [
            'removed' => [],
            'deleted_files' => [],
        ];

        if (empty($unusedKeys)) {
            return $report;
        }

        // Split keys into JSON (no dot) and PHP (have dot -> group.item)
        $jsonKeys = array_values(array_filter($unusedKeys, fn ($k) => ! str_contains($k, '.')));
        $phpKeys = array_values(array_filter($unusedKeys, fn ($k) => str_contains($k, '.')));

        // Clean JSON files like resources/lang/en.json
        foreach ($langRoots as $langRoot) {
            if (! $this->files->isDirectory($langRoot)) {
                continue;
            }

            // Any locale JSON file directly under $langRoot
            $finder = new Finder;
            $finder->in($langRoot)->depth('== 0')->files()->name('*.json');
            foreach ($finder as $jsonFile) {
                $path = $jsonFile->getRealPath();
                if (! $path) {
                    continue;
                }
                $content = json_decode($this->files->get($path), true) ?: [];
                $changed = false;
                foreach ($jsonKeys as $key) {
                    if (array_key_exists($key, $content)) {
                        unset($content[$key]);
                        $report['removed'][] = $key;
                        $changed = true;
                    }
                }
                if ($changed) {
                    if (empty($content)) {
                        $this->files->delete($path);
                        $report['deleted_files'][] = $path;
                    } else {
                        $this->files->put($path, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
                    }
                }
            }
        }

        // Map PHP keys to their group (file path without locale and extension)
        $keysByGroup = [];
        foreach ($phpKeys as $key) {
            [$group, $item] = explode('.', $key, 2);
            $keysByGroup[$group][] = $item; // item may be nested path like "a.b.c"
        }

        // For each lang root, iterate all locale PHP files and remove matching keys
        foreach ($langRoots as $langRoot) {
            if (! $this->files->isDirectory($langRoot)) {
                continue;
            }

            $finder = new Finder;
            $finder->in($langRoot)->files()->name('*.php');

            foreach ($finder as $phpFile) {
                $real = $phpFile->getRealPath();
                if (! $real) {
                    continue;
                }

                // Compute group for this file: {langRoot}/{locale}/{group}.php -> group is relative path after locale
                $relative = Str::after($real, rtrim($langRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
                $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative);

                // remove leading {locale}\ or {locale}/
                $parts = explode(DIRECTORY_SEPARATOR, $relative);
                if (count($parts) < 2) {
                    // unexpected structure
                    continue;
                }
                array_shift($parts); // drop locale
                $fileNoExt = implode(DIRECTORY_SEPARATOR, $parts);
                $fileNoExt = Str::beforeLast($fileNoExt, '.php');
                $group = str_replace(DIRECTORY_SEPARATOR, '/', $fileNoExt); // group uses forward slashes

                if (! isset($keysByGroup[$group])) {
                    // No keys planned for this group
                    continue;
                }

                $data = $this->files->getRequire($real);
                if (! is_array($data)) {
                    $data = [];
                }

                $before = $data;
                foreach ($keysByGroup[$group] as $itemPath) {
                    if (Arr::has($data, $itemPath)) {
                        Arr::forget($data, $itemPath);
                        $report['removed'][] = $group.'.'.$itemPath;
                    }
                }

                if ($data !== $before) {
                    if (empty($data)) {
                        $this->files->delete($real);
                        $report['deleted_files'][] = $real;
                    } else {
                        $export = $this->varExportShort($data);
                        $this->files->put($real, "<?php\n\nreturn ".$export.";\n");
                    }
                }
            }
        }

        // Normalize report arrays
        $report['removed'] = array_values(array_unique($report['removed']));
        sort($report['removed']);
        $report['deleted_files'] = array_values(array_unique($report['deleted_files']));
        sort($report['deleted_files']);

        return $report;
    }

    /**
     * Collect all translation keys defined across given lang paths.
     * Handles both PHP array files and JSON files.
     *
     * @param  array<string>  $langPaths
     * @return array<string>
     */
    protected function getDefinedTranslationKeys(array $langPaths): array
    {
        $keys = [];

        $finder = new Finder;
        $finder->in($langPaths)->files()->name(['*.php', '*.json']);

        foreach ($finder as $file) {
            $ext = $file->getExtension();
            if ($ext === 'json') {
                $content = json_decode($file->getContents(), true) ?: [];
                $keys = array_merge($keys, array_keys($content));

                continue;
            }

            // PHP array file under {langRoot}/{locale}/{group}.php
            $real = $file->getRealPath();
            if (! $real) {
                continue;
            }
            $data = $this->files->getRequire($real);
            if (! is_array($data)) {
                continue;
            }

            // Determine group path after locale
            foreach ($langPaths as $langRoot) {
                $langRoot = rtrim($langRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
                if (! str_starts_with($real, $langRoot)) {
                    continue;
                }
                $relative = Str::after($real, $langRoot);
                $parts = explode(DIRECTORY_SEPARATOR, $relative);
                if (count($parts) < 2) {
                    continue;
                }
                array_shift($parts); // drop locale
                $fileNoExt = implode(DIRECTORY_SEPARATOR, $parts);
                $fileNoExt = Str::beforeLast($fileNoExt, '.php');
                $group = str_replace(DIRECTORY_SEPARATOR, '/', $fileNoExt);

                $flat = Arr::dot($data);
                foreach ($flat as $k => $_) {
                    $keys[] = $group.'.'.$k;
                }
                break; // processed with matching lang root
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    /**
     * Scan project code for used translation keys.
     *
     * @param  array<string>  $scanPaths
     * @return array<string>
     */
    protected function findUsedTranslationKeys(array $scanPaths): array
    {
        $keys = [];

        $functions = config('translation-cleaner.translation_functions', ['__', 'trans', 'trans_choice', '@lang', '@choice']);
        // Build patterns for helpers and blade directives
        $helperFns = array_filter($functions, fn ($f) => ! str_starts_with($f, '@'));
        $directives = array_filter($functions, fn ($f) => str_starts_with($f, '@'));

        $patterns = [];
        if (! empty($helperFns)) {
            $fnAlternation = implode('|', array_map(fn ($f) => preg_quote($f, '/'), $helperFns));
            // Match __("key") or trans('key') or trans_choice('key', ...)
            $patterns[] = '/(?:'.$fnAlternation.')\(\s*[\'"\"]([^\'"\"]+)[\'"\"]/u';
        }
        foreach ($directives as $d) {
            $name = ltrim($d, '@');
            // @lang('key') or @choice('key', ...)
            $patterns[] = '/@'.preg_quote($name, '/').'\(\s*[\'"\"]([^\'"\"]+)[\'"\"]/u';
        }

        $filePatterns = config('translation-cleaner.file_extensions', ['*.php', '*.blade.php']);
        // Normalize simple extensions like 'php' into '*.php'
        $filePatterns = array_map(function ($p) {
            $p = trim($p);
            if ($p === '') {
                return $p;
            }
            if (str_starts_with($p, '*')) {
                return $p;
            }
            if (! str_contains($p, '.')) {
                return '*.'.$p;
            }

            return '*.'.ltrim($p, '*.');
        }, $filePatterns);

        $finder = new Finder;
        $finder->in($scanPaths)->files()->name($filePatterns);

        foreach ($finder as $file) {
            $contents = $file->getContents();
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $contents, $m)) {
                    // capture group 1 contains the key
                    $keys = array_merge($keys, $m[1]);
                }
            }
        }

        // Normalize keys
        $keys = array_values(array_unique(array_map(static fn ($k) => trim(stripcslashes($k)), $keys)));
        sort($keys);

        return $keys;
    }
}
