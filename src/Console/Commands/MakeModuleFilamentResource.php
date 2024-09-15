<?php

namespace Lakasir\LakasirModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleFilamentResource extends Command
{
    protected $signature = 'lakasir-module:resource';

    protected $description = 'Create a new Filament resource within a module';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $module = Str::studly($this->ask('module'));
        $resource = Str::studly($this->ask('resource'));

        $modulePath = base_path("modules/{$module}");

        // Ensure the module exists
        if (! File::exists($modulePath)) {
            $this->error("Module '{$module}' does not exist.");

            return;
        }

        $resourcePath = "{$modulePath}/Filament/Resources/{$resource}Resource.php";

        if (File::exists($resourcePath)) {
            $this->error("Resource '{$resource}' already exists in module '{$module}'.");

            return;
        }

        $this->generateFilamentResource($module, $resource);

        $this->info("Filament resource '{$resource}' created successfully in module '{$module}'.");
    }

    protected function generateFilamentResource($module, $resource)
    {
        $stubPath = base_path('vendor/filament/filament/resources/stubs/Resource.stub');

        $moduleResourcePath = base_path("modules/{$module}/Filament/Resources");
        if (! File::exists($moduleResourcePath)) {
            File::makeDirectory($moduleResourcePath, 0755, true);
        }

        $stub = File::get($stubPath);

        $content = str_replace(
            ['{{ namespace }}', '{{ resource }}', '{{ resourceLowerCase }}'],
            ["Modules\\{$module}\\Filament\\Resources", $resource, Str::snake($resource)],
            $stub
        );

        $filePath = "{$moduleResourcePath}/{$resource}Resource.php";
        File::put($filePath, $content);
    }
}
