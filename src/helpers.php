<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

if (! function_exists('getModules')) {
    function getModules(): array
    {
        return File::isDirectory(base_path('modules')) ? File::directories(base_path('modules')) : [];
    }
}

if (! function_exists('getPackageModuleName')) {
    function getPackageModuleName(string $module): string
    {
        $composerFile = json_decode(File::get(base_path("modules/{$module}/composer.json")), 1);

        return $composerFile['name'];
    }
}

if (! function_exists('isModuleDevCheckout')) {
    /**
     * Whether `modules/<folder>` is a developer checkout rather than an
     * installed release, so the installer must never replace or delete it.
     *
     * A checkout is either a plain clone (`.git` directory), a git submodule
     * (`.git` file) or a symlink into one — the loader and the guards both
     * follow the link, so `file_exists` covers every case. `is_link` is checked
     * first because Laravel's `deleteDirectory` would empty the real tree.
     */
    function isModuleDevCheckout(string $path): bool
    {
        return is_link($path) || file_exists($path.'/.git');
    }
}

if (! function_exists('getModuleVersion')) {
    /**
     * Version of an installed module: its git tag when the folder is a
     * checkout, else the version its release manifest declared.
     *
     * A checkout's composer.json is hand-maintained and drifts (Warehouse sat
     * at 1.0.0 on v1.2.0), so the tag is the only trustworthy source there.
     */
    function getModuleVersion(string $path, ?string $declared = null): string
    {
        if (file_exists($path.'/.git')) {
            $process = new Process(['git', 'describe', '--tags', '--always'], $path);
            $process->setTimeout(2)->run();

            if ($process->isSuccessful() && trim($process->getOutput()) !== '') {
                return trim($process->getOutput());
            }
        }

        return $declared ?: '-';
    }
}
