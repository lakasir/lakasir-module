<?php

namespace Lakasir\LakasirModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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
        $packageName = str(getPackageModuleName($module))->replace('/', '_');

        $migrationTable = 'migrations_'.$packageName;
        config(['database.migrations' => $migrationTable]);

        if (! Schema::hasTable($migrationTable)) {
            $this->info("Migration table '{$migrationTable}' does not exist. Creating it...");
            $this->createMigrationTable($migrationTable);
        }

        $options = $this->option();

        $artisanOptions = [
            '--realpath' => true,
            '--path' => [$migrationPath],
        ];

        if ($options['refresh']) {
            $this->info("Refreshing migrations for module '{$module}'...");
            Artisan::call('migrate:refresh', $artisanOptions);
        } elseif ($options['rollback']) {
            $this->info("Rolling back migrations for module '{$module}'...");
            Artisan::call('migrate:rollback', $artisanOptions);
        } else {
            $this->info("Running migrations for module '{$module}'...");
            Artisan::call('migrate', $artisanOptions);
        }

        $this->info(Artisan::output());
    }

    /**
     * Create the migration table if it doesn't exist.
     *
     * @return void
     */
    protected function createMigrationTable(string $tableName)
    {
        Schema::create($tableName, function ($table) {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });

        $this->info("Migration table '{$tableName}' created successfully.");
    }
}
