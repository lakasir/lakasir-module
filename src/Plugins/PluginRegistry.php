<?php

namespace Lakasir\LakasirModule\Plugins;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads the community module index.
 *
 * The index is a plain JSON file in a git repository, so adding a module is a
 * pull request there; no server of ours is involved.
 */
class PluginRegistry
{
    private const CACHE_KEY = 'lakasir-module.registry.index';

    /**
     * The index, served from cache when it was fetched before.
     *
     * The fetch happens outside the cache write so a failed request caches
     * nothing: `remember()` would store a stale value for the whole TTL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function index(): array
    {
        $cached = $this->cached();

        if ($cached !== null) {
            return $cached;
        }

        $modules = $this->modules();

        Cache::put(self::CACHE_KEY, $modules, (int) config('lakasir-module.registry_cache_ttl', 3600));

        return $modules;
    }

    /**
     * The cached index, or null when it was never fetched successfully.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function cached(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? $cached : null;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function modules(): array
    {
        $url = config('lakasir-module.registry_url');

        if (blank($url)) {
            return [];
        }

        $modules = str_starts_with($url, 'file://')
            ? $this->readFile($url)
            : $this->fetch($url);

        if (! is_array($modules)) {
            throw new RuntimeException(__('The module registry has no "modules" list.'));
        }

        return array_values(array_filter($modules, fn ($module) => is_array($module) && ! empty($module['name'])));
    }

    /**
     * Download a module's release archive into a temporary file.
     */
    public function download(array $module): string
    {
        $url = $module['download'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new RuntimeException(__('This module has no download URL.'));
        }

        $path = tempnam(sys_get_temp_dir(), 'plugin-').'.zip';

        if (str_starts_with($url, 'file://')) {
            if (! @copy(substr($url, strlen('file://')), $path)) {
                @unlink($path);

                throw new RuntimeException(__('Could not read the module archive (:path).', [
                    'path' => substr($url, strlen('file://')),
                ]));
            }

            return $path;
        }

        $response = Http::timeout(max(30, (int) config('lakasir-module.timeout', 10)))
            ->withOptions(['sink' => $path])
            ->get($url);

        if (! $response->successful()) {
            @unlink($path);

            throw new RuntimeException(__('Could not download the module (:status).', [
                'status' => $response->status(),
            ]));
        }

        return $path;
    }

    /**
     * @return array<int, mixed>|null
     */
    private function fetch(string $url): ?array
    {
        $response = Http::timeout((int) config('lakasir-module.timeout', 10))
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException(__('Could not reach the module registry (:status).', [
                'status' => $response->status(),
            ]));
        }

        return $response->json('modules');
    }

    /**
     * A `file://` index keeps the registry testable without a network.
     *
     * @return array<int, mixed>|null
     */
    private function readFile(string $url): ?array
    {
        $path = substr($url, strlen('file://'));
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(__('Could not read the module registry (:path).', ['path' => $path]));
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? ($decoded['modules'] ?? null) : null;
    }
}
