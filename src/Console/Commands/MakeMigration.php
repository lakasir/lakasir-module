<?php

namespace Lakasir\LakasirModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

class MakeMigration extends Command
{
    use UpdateNamespaceTrait;

    protected $signature = 'lakasir-module:migration';

    protected $description = 'Create a new migration within a module';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $module = Str::studly(text(
            label: 'What module should this migration be added to?',
            placeholder: 'Accounting',
            required: true
        ));

        $modulePath = base_path("modules/{$module}");

        if (! File::exists($modulePath)) {
            $this->error("Module '{$module}' does not exist.");

            return;
        }

        $migrationName = Str::snake(text(
            label: 'What is the migration name?',
            placeholder: 'create_invoices_table',
            required: true
        ));

        $migrationPath = "{$modulePath}/database/migrations";

        if (! File::exists($migrationPath)) {
            File::makeDirectory($migrationPath, 0755, true);
        }

        Artisan::call('make:migration', [
            'name' => $migrationName,
            '--path' => "modules/{$module}/database/migrations",
        ]);

        $this->info("Migration '{$migrationName}' created successfully in module '{$module}'.");
    }
}
