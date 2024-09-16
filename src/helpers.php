<?php

use Illuminate\Support\Facades\File;

if (! function_exists('getModules')) {
    function getModules(): array
    {
        return File::isDirectory(base_path('modules')) ? File::directories(base_path('modules')) : [];
    }
}
