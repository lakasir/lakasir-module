<?php

/*
 * Configuration for lakasir/lakasir-module.
 *
 * Published to config/lakasir-module.php with:
 *   php artisan vendor:publish --tag=lakasir-module-config
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Module registry
    |--------------------------------------------------------------------------
    |
    | A JSON file listing installable modules. `index.json` in a git
    | repository works: adding a module is a pull request against it.
    | Set to null to disable installing from the registry.
    |
    */

    'registry_url' => env('LAKASIR_MODULE_REGISTRY_URL', 'https://raw.githubusercontent.com/lakasir/module-registry/main/index.json'),

    'timeout' => env('LAKASIR_MODULE_REGISTRY_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Archives
    |--------------------------------------------------------------------------
    */

    'max_upload_kb' => env('LAKASIR_MODULE_MAX_UPLOAD_KB', 32768),

    /*
    |--------------------------------------------------------------------------
    | Shell
    |--------------------------------------------------------------------------
    |
    | `composer install` runs after extracting a module so its own vendor/
    | autoloader exists, which the module loader requires.
    |
    */

    'composer_binary' => env('LAKASIR_MODULE_COMPOSER_BINARY'),

    'composer_timeout' => env('LAKASIR_MODULE_COMPOSER_TIMEOUT', 300),

];
