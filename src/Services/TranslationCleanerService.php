<?php

declare(strict_types=1);

namespace Artryazanov\ArtisanTranslator\Services;

use Artryazanov\ArtisanTranslator\Concerns\ExportsShortArrays;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * Service responsible for discovering defined translation keys, scanning code for used keys,
 * computing the difference, and removing unused keys from language files.
 */
class TranslationCleanerService
{
    use ExportsShortArrays;

    /**
     * @param Filesystem $files
     * @param TranslationCodeScanner $scanner
     * @param TranslationRepository $repository
     */
    public function __construct(
        private readonly Filesystem $files,
        private readonly TranslationCodeScanner $scanner,
        private readonly TranslationRepository $repository
    ) {}

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
        $usedKeys = $this->scanner->findUsedTranslationKeys($scanPaths);

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
            $finder = new Finder();
            $finder->in($langRoot)->depth('== 0')->files()->name('*.json');
            foreach ($finder as $jsonFile) {
                $path = $jsonFile->getRealPath();
                if (! $path) {
                    continue;
                }
                
                $content = $this->repository->loadJson($path);
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
                        $this->repository->delete($path);
                        $report['deleted_files'][] = $path;
                    } else {
                        $this->repository->saveJson($path, $content);
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

            $finder = new Finder();
            $finder->in($langRoot)->files()->name('*.php');

            foreach ($finder as $phpFile) {
                $real = $phpFile->getRealPath();
                if (! $real) {
                    continue;
                }

                // Compute group for this file: {langRoot}/{locale}/{group}.php
                $relative = Str::after($real, rtrim($langRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
                $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative);

                $parts = explode(DIRECTORY_SEPARATOR, $relative);
                if (count($parts) < 2) {
                    continue;
                }
                array_shift($parts); // drop locale
                $fileNoExt = implode(DIRECTORY_SEPARATOR, $parts);
                $fileNoExt = Str::beforeLast($fileNoExt, '.php');
                $group = str_replace(DIRECTORY_SEPARATOR, '/', $fileNoExt);

                if (! isset($keysByGroup[$group])) {
                    continue;
                }

                $data = $this->repository->load($real);

                $before = $data;
                foreach ($keysByGroup[$group] as $itemPath) {
                    if (Arr::has($data, $itemPath)) {
                        Arr::forget($data, $itemPath);
                        $report['removed'][] = $group.'.'.$itemPath;
                    }
                }

                if ($data !== $before) {
                    if (empty($data)) {
                        $this->repository->delete($real);
                        $report['deleted_files'][] = $real;
                    } else {
                         $this->repository->save($real, $data);
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

        $finder = new Finder();
        $finder->in($langPaths)->files()->name(['*.php', '*.json']);

        foreach ($finder as $file) {
            $ext = $file->getExtension();
            $real = $file->getRealPath();
            if (! $real) {
                continue;
            }

            if ($ext === 'json') {
                $content = $this->repository->loadJson($real);
                $keys = array_merge($keys, array_keys($content));
                continue;
            }

            // PHP array file under {langRoot}/{locale}/{group}.php
            $data = $this->repository->load($real);
            
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
}
