<?php

namespace Lakasir\LakasirModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

class Migrate extends Command
{
    protected $signature = 'lakasir-module:migrate {module?} {--rollback} {--refresh}';

    protected $description = 'Run the migrations within a specific module';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $module = Str::studly(
            $this->argument('module')
            ? $this->argument('module')
            : text(
                label: 'What module would you like to migrate?',
                placeholder: 'Accounting',
                required: true
            )
        );

        $modulePath = base_path("modules/{$module}");

        if (! File::exists($modulePath)) {
            $this->error("Module '{$module}' does not exist.");

            return;
        }

        $migrationPath = "{$modulePath}/database/migrations";

        if (! File::exists($migrationPath)) {
            $this->error("No migrations found for module '{$module}'.");

            return;
        }

        $options = $this->option();

        $artisanOptions = [
            '--path' => "modules/{$module}/database/migrations",
        ];

        if ($options['refresh']) {
            Artisan::call('migrate:refresh', $artisanOptions);
        }

        if ($options['rollback']) {
            Artisan::call('migrate:rollback', $artisanOptions);
        }

        if (! $options['refresh'] && ! $options['rollback']) {
            Artisan::call('migrate', $artisanOptions);
        }

        $this->info(Artisan::output());
    }
}
