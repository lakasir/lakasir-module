<?php

namespace Lakasir\LakasirModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

class MakeController extends Command
{
    use UpdateNamespaceTrait;

    protected $signature = 'lakasir-module:controller';

    protected $description = 'Create a new controller within a module';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $module = Str::studly(text(
            label: 'What module should this controller be added to?',
            placeholder: 'Accounting',
            required: true
        ));

        $modulePath = base_path("modules/{$module}");

        if (! File::exists($modulePath)) {
            $this->error("Module '{$module}' does not exist.");

            return;
        }

        $controllerName = Str::studly(text(
            label: 'What is the controller name?',
            placeholder: 'InvoiceController',
            required: true
        ));

        $controllersPath = "{$modulePath}/src/Http/Controllers";

        if (! File::exists($controllersPath)) {
            File::makeDirectory($controllersPath, 0755, true);
        }

        $controllerFilePath = "{$controllersPath}/{$controllerName}.php";

        if (File::exists($controllerFilePath)) {
            $this->error("Controller '{$controllerName}' already exists in module '{$module}'.");

            return;
        }

        $this->generateControllerFile($module, $controllerName);

        $this->info("Controller '{$controllerName}' created successfully in module '{$module}'.");
    }

    protected function generateControllerFile($module, $controllerName)
    {
        $moduleControllerPath = base_path("modules/{$module}/src/Http/Controllers");
        File::ensureDirectoryExists($moduleControllerPath);

        Artisan::call('make:controller', [
            'name' => $controllerName,
        ]);

        $defaultControllerPath = app_path("Http/Controllers/{$controllerName}.php");
        $newControllerPath = "{$moduleControllerPath}/{$controllerName}.php";

        if (File::exists($defaultControllerPath)) {
            File::move($defaultControllerPath, $newControllerPath);
            $this->updateNamespace($newControllerPath, "Modules\\{$module}\\Http\\Controllers");
            $this->info("Controller '{$controllerName}Controller' moved to module '{$module}' and namespace updated.");
        } else {
            $this->error("Controller '{$controllerName}Controller' not found to move.");
        }
    }
}
