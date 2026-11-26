<?php

namespace Lakasir\LakasirModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Lakasir\LakasirModule\Plugins\MigrationLedger;

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

        $migrationTable = MigrationLedger::nameFor(getPackageModuleName($module));
        MigrationLedger::adopt($migrationTable, $migrationPath);

        if ($this->option('refresh')) {
            $this->info("Refreshing migrations for module '{$module}'...");
            $ran = MigrationLedger::refresh($migrationTable, $migrationPath);
        } elseif ($this->option('rollback')) {
            $this->info("Rolling back migrations for module '{$module}'...");
            $ran = MigrationLedger::rollback($migrationTable, $migrationPath);
        } else {
            $this->info("Running migrations for module '{$module}'...");
            $ran = MigrationLedger::run($migrationTable, $migrationPath);
        }

        $this->info($ran === [] ? 'Nothing to migrate.' : implode(PHP_EOL, $ran));
    }
}
