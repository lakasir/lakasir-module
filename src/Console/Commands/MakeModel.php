<?php

namespace Lakasir\LakasirModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

class MakeModel extends Command
{
    use UpdateNamespaceTrait;

    protected $signature = 'lakasir-module:model';

    protected $description = 'Create a new model within a module';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $module = Str::studly(text(
            label: 'What module should this model be added to?',
            placeholder: 'Accounting',
            required: true
        ));

        $modulePath = base_path("modules/{$module}");

        if (! File::exists($modulePath)) {
            $this->error("Module '{$module}' does not exist.");

            return;
        }

        $modelName = Str::studly(text(
            label: 'What is the model name?',
            placeholder: 'Invoice',
            required: true
        ));

        $options = multiselect(
            label: 'Would you like any of these to be created?',
            options: [
                '-m' => 'migration',
                '-c' => 'controller',
                '-s' => 'seeder',
                '-f' => 'factory',
            ]
        );

        $modelsPath = "{$modulePath}/src/Models";

        if (! File::exists($modelsPath)) {
            File::makeDirectory($modelsPath, 0755, true);
        }

        $this->generateModelFromStub($module, $modelName);

        $this->generateAdditionalFiles($module, $modelName, $options);

        $this->info("Model '{$modelName}' created successfully in module '{$module}'.");
    }

    protected function generateModelFromStub($module, $modelName)
    {
        $stubPath = base_path('vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/model.stub');

        if (! File::exists($stubPath)) {
            $this->error('Model stub file not found.');

            return;
        }

        $stub = File::get($stubPath);

        $content = str_replace(
            ['{{ namespace }}', '{{ class }}'],
            ["Modules\\{$module}\\Models", $modelName],
            $stub
        );

        $modelFilePath = base_path("modules/{$module}/src/Models/{$modelName}.php");

        File::put($modelFilePath, $content);

        $this->info("Model '{$modelName}' created successfully in {$modelFilePath}.");
    }

    protected function generateAdditionalFiles($module, $modelName, $options)
    {
        $moduleDatabasePath = base_path("modules/{$module}/database/migrations");
        $moduleControllerPath = base_path("modules/{$module}/src/Http/Controllers");
        $moduleSeederPath = base_path("modules/{$module}/database/seeders");
        $moduleFactoryPath = base_path("modules/{$module}/database/factories");
        // Ensure the directories exist
        File::ensureDirectoryExists($moduleDatabasePath);
        File::ensureDirectoryExists($moduleControllerPath);
        File::ensureDirectoryExists($moduleSeederPath);
        File::ensureDirectoryExists($moduleFactoryPath);

        if (in_array('-m', $options)) {
            Artisan::call('make:migration', [
                'name' => "create{$modelName}_table",
                '--path' => "modules/{$module}/database/migrations",
            ]);
            $this->info("Migration for '{$modelName}' created in module '{$module}'.");
        }

        if (in_array('-c', $options)) {
            Artisan::call('make:controller', [
                'name' => "{$modelName}Controller",
            ]);

            $defaultControllerPath = app_path("Http/Controllers/{$modelName}Controller.php");
            $newControllerPath = "{$moduleControllerPath}/{$modelName}Controller.php";

            if (File::exists($defaultControllerPath)) {
                File::move($defaultControllerPath, $newControllerPath);
                $this->updateNamespace($newControllerPath, "Modules\\{$module}\\Http\\Controllers");
                $this->info("Controller '{$modelName}Controller' moved to module '{$module}' and namespace updated.");
            } else {
                $this->error("Controller '{$modelName}Controller' not found to move.");
            }
        }

        if (in_array('-s', $options)) {
            Artisan::call('make:seeder', [
                'name' => "{$modelName}Seeder",
            ]);

            $defaultSeederPath = database_path("seeders/{$modelName}Seeder.php");
            $newSeederPath = "{$moduleSeederPath}/{$modelName}Seeder.php";

            if (File::exists($defaultSeederPath)) {
                File::move($defaultSeederPath, $newSeederPath);
                $this->updateNamespace($newSeederPath, "Modules\\{$module}\\Database\\Seeders");
                $this->info("Seeder '{$modelName}Seeder' moved to module '{$module}' and namespace updated.");
            } else {
                $this->error("Seeder '{$modelName}Seeder' not found to move.");
            }
        }

        if (in_array('-f', $options)) {
            Artisan::call('make:factory', [
                'name' => "{$modelName}Factory",
            ]);

            $defaultFactoryPath = database_path("factories/{$modelName}Factory.php");
            $newFactoryPath = "{$moduleFactoryPath}/{$modelName}Factory.php";

            if (File::exists($defaultFactoryPath)) {
                File::move($defaultFactoryPath, $newFactoryPath);
                $this->updateNamespace($newFactoryPath, "Modules\\{$module}\\Database\\Factories");
                $this->info("Factory '{$modelName}Factory' moved to module '{$module}' and namespace updated.");
            } else {
                $this->error("Factory '{$modelName}Factory' not found to move.");
            }
        }
    }
}
