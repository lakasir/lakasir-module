<?php

namespace Lakasir\LakasirModule\Plugins;

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
    /**
     * @return array<int, array<string, mixed>>
     */
    public function modules(): array
    {
        $url = config('lakasir-module.registry_url');

        if (blank($url)) {
            return [];
        }

        $response = Http::timeout((int) config('lakasir-module.timeout', 10))
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException(__('Could not reach the module registry (:status).', [
                'status' => $response->status(),
            ]));
        }

        $modules = $response->json('modules');

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
}
