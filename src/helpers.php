<?php

use Illuminate\Support\Facades\File;

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
