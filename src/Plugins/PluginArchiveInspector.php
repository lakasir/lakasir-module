<?php

namespace Lakasir\LakasirModule\Plugins;

use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Reads a module archive without extracting it.
 *
 * Everything here is a validation step: an archive that fails to satisfy
 * these rules never reaches `modules/`.
 */
class PluginArchiveInspector
{
    /** A release archive is small; anything larger is not a module. */
    public const MAX_BYTES = 32 * 1024 * 1024;

    public const MAX_FILES = 5000;

    /**
     * @throws RuntimeException when the archive is not a module
     */
    public function inspect(string $path): PluginArchive
    {
        $size = @filesize($path);

        if ($size === false || $size === 0) {
            throw new RuntimeException(__('The uploaded file is empty.'));
        }

        if ($size > self::MAX_BYTES) {
            throw new RuntimeException(__('The archive is larger than :size MB.', [
                'size' => (int) (self::MAX_BYTES / 1024 / 1024),
            ]));
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException(__('The file is not a readable zip archive.'));
        }

        try {
            $entries = $this->entries($zip);

            if (count($entries) > self::MAX_FILES) {
                throw new RuntimeException(__('The archive contains too many files.'));
            }

            $this->assertNoTraversal($entries);

            $prefix = $this->commonPrefix($entries);
            $manifest = $this->readComposerJson($zip, $prefix);

            $name = $manifest['name'] ?? null;

            if (! is_string($name) || ! preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#i', $name)) {
                throw new RuntimeException(__('composer.json is missing a valid "name" (vendor/package).'));
            }

            $folder = $this->deriveFolder($manifest, $name);

            if ($folder === '') {
                throw new RuntimeException(__('Cannot derive a module folder name from ":name".', ['name' => $name]));
            }

            $provider = "{$prefix}src/{$folder}ServiceProvider.php";

            if (! in_array($provider, $entries, true)) {
                throw new RuntimeException(__('The archive has no :path; it does not look like a lakasir module.', [
                    'path' => "src/{$folder}ServiceProvider.php",
                ]));
            }

            return new PluginArchive(
                path: $path,
                rootPrefix: $prefix,
                name: $name,
                folder: $folder,
                author: $manifest['authors'][0]['name'] ?? null,
                version: $manifest['version'] ?? null,
                fileCount: count($entries),
                bytes: $size,
                checksum: hash_file('sha256', $path),
            );
        } finally {
            $zip->close();
        }
    }

    /**
     * The module folder, which must match the provider class the module loader
     * looks for: `Modules\\<Folder>\\<Folder>ServiceProvider`.
     *
     * The `composer.json` `autoload.psr-4` namespace is authoritative because
     * that is what the provider file declares; the package `name` is only a
     * fallback, and the two disagree in some published modules
     * (`lakasir/debtsandreceivables` declaring `Modules\DebtsAndReceivables`).
     *
     * @param  array<string, mixed>  $manifest
     */
    private function deriveFolder(array $manifest, string $name): string
    {
        foreach (array_keys((array) ($manifest['autoload']['psr-4'] ?? [])) as $namespace) {
            // "Modules\\DebtsAndReceivables\\" -> ["Modules", "DebtsAndReceivables", ""]
            $segments = array_values(array_filter(explode('\\', trim($namespace, '\\'))));

            if (count($segments) >= 2 && $segments[0] === 'Modules') {
                return $segments[1];
            }
        }

        return Str::of($name)->after('/')->studly()->replaceMatches('/[^A-Za-z0-9]/', '')->value();
    }

    /**
     * @return array<int, string>
     */
    private function entries(ZipArchive $zip): array
    {
        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name !== false && ! str_ends_with($name, '/')) {
                $entries[] = $name;
            }
        }

        return $entries;
    }

    /**
     * Reject absolute paths and `..` segments before anything is written.
     *
     * @param  array<int, string>  $entries
     */
    private function assertNoTraversal(array $entries): void
    {
        foreach ($entries as $entry) {
            $normalized = str_replace('\\', '/', $entry);

            if (str_starts_with($normalized, '/') || preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
                throw new RuntimeException(__('The archive contains an unsafe path.'));
            }
        }
    }

    /**
     * GitHub archives wrap everything in one top-level directory; a hand-made
     * zip may not. Either way, `composer.json` must sit at that root.
     *
     * @param  array<int, string>  $entries
     */
    private function commonPrefix(array $entries): string
    {
        $prefixes = [];

        foreach ($entries as $entry) {
            $prefixes[Str::before($entry, '/')] = true;
        }

        if (count($prefixes) === 1 && ! in_array('composer.json', $entries, true)) {
            return array_key_first($prefixes).'/';
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function readComposerJson(ZipArchive $zip, string $prefix): array
    {
        $path = $prefix.'composer.json';
        $code = $zip->locateName($path);

        if ($code === false) {
            throw new RuntimeException(__('The archive has no composer.json.'));
        }

        $contents = $zip->getFromIndex($code);
        $manifest = is_string($contents) ? json_decode($contents, true) : null;

        if (! is_array($manifest)) {
            throw new RuntimeException(__('composer.json in the archive is not valid JSON.'));
        }

        return $manifest;
    }
}
