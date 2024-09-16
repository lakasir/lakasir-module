<?php

namespace Lakasir\LakasirModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

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
        $module = Str::studly(text(
            label: 'What module should this resource be added to?',
            placeholder: 'Accounting',
            required: true
        ));
        $resource = Str::studly(text(
            label: 'What resource should this be?',
            placeholder: 'Customer',
            required: true
        ));

        $modulePath = base_path("modules/{$module}");

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

    // TODO:
    protected function generateFilamentResource($module, $resource)
    {
        // $this->callSilent('make:filament-resource', [
        //     'name' => $resource,
        // ]);

        // $stubPath = base_path('vendor/filament/filament/stubs/Resource.stub');
        //
        // $moduleResourcePath = base_path("modules/{$module}/src/Filament/Resources");
        // if (! File::exists($moduleResourcePath)) {
        //     File::makeDirectory($moduleResourcePath, 0755, true);
        // }
        //
        // $stub = File::get($stubPath);
        //
        // $content = str_replace(
        //     [
        //         '{{ namespace }}',               // Placeholder for the namespace
        //         '{{ resource }}',                // Placeholder for the resource class name
        //         '{{ resourceClass }}',           // Placeholder for the full resource class name
        //         '{{ modelClass }}',              // Placeholder for the model class name
        //         '{{ model }}',                   // Placeholder for the model
        //         '{{ formSchema }}',
        //         '{{ tableColumns }}',
        //         '{{ tableFilters }}',
        //         '{{ tableActions }}',
        //         '{{ tableBulkActions }}',
        //         '{{ relations }}',
        //         '{{ pages }}',
        //         '{{ eloquentQuery }}',
        //     ],
        //     [
        //         "Modules\\{$module}\\Filament\\Resources", // Actual namespace
        //         $resource,                                // Actual resource name
        //         "{$resource}Resource",                    // Actual resource class name
        //         "{$resource}Model",                       // Placeholder model (you can customize this part)
        //         "{$resource}",                            // Actual model name
        //     ],
        //     $stub
        // );
        //
        // // Define the file path where the resource will be saved
        // $filePath = "{$moduleResourcePath}/{$resource}Resource.php";
        //
        // // Save the generated content to the file
        // File::put($filePath, $content);
    }
}
