<?php

namespace Lakasir\LakasirModule\Plugins;

use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;

/**
 * A module's migrations are recorded in a ledger of their own, so they can be
 * rolled back without touching the application's `migrations` table.
 *
 * `migrate` takes its migrator from the container, which reads the ledger name
 * once, long before a module's table name is known; pointing
 * `database.migrations` at the module's ledger therefore goes unnoticed and the
 * records land against the application. The module's migrations are run through
 * a migrator of their own instead.
 */
class MigrationLedger
{
    public static function nameFor(string $package): string
    {
        return 'migrations_'.str_replace('/', '_', $package);
    }

    /**
     * Run the module's pending migrations, returning the names of the ones that
     * ran.
     *
     * @return array<int, string>
     */
    public static function run(string $table, string $path): array
    {
        return self::migrator($table)->run([$path]);
    }

    /**
     * Roll back the module's last batch.
     *
     * @return array<int, string>
     */
    public static function rollback(string $table, string $path): array
    {
        $migrator = self::migrator($table);

        return $migrator->rollback([$path]);
    }

    /**
     * Roll back every batch the module recorded, then run its migrations again.
     *
     * @return array<int, string>
     */
    public static function refresh(string $table, string $path): array
    {
        $migrator = self::migrator($table);

        $recorded = $migrator->getRepository()->getRan();

        while ($recorded !== []) {
            $migrator->rollback([$path]);

            $remaining = $migrator->getRepository()->getRan();

            if ($remaining === $recorded) {
                break;
            }

            $recorded = $remaining;
        }

        return $migrator->run([$path]);
    }

    /**
     * Move the records of this module's migration files from the application's
     * ledger into the module's own.
     *
     * Files migrated before the module had a ledger are recorded against the
     * application, which would make the module look unmigrated to its own
     * ledger and re-run against tables that already exist.
     */
    public static function adopt(string $table, string $path): void
    {
        $applicationTable = config('database.migrations');
        $applicationTable = is_array($applicationTable)
            ? ($applicationTable['table'] ?? null)
            : $applicationTable;

        if ($applicationTable === $table || ! Schema::hasTable($applicationTable) || ! Schema::hasTable($table)) {
            return;
        }

        $files = collect(glob($path.'/*.php') ?: [])
            ->map(fn (string $file) => basename($file, '.php'))
            ->values()
            ->all();

        if ($files === []) {
            return;
        }

        $connection = Schema::getConnection();
        $rows = $connection->table($applicationTable)
            ->whereIn('migration', $files)
            ->orderBy('batch')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $recorded = $connection->table($table)->pluck('migration')->all();

        foreach ($rows as $row) {
            if (in_array($row->migration, $recorded, true)) {
                continue;
            }

            $connection->table($table)->insert([
                'migration' => $row->migration,
                'batch' => $row->batch,
            ]);
        }

        $connection->table($applicationTable)->whereIn('migration', $rows->pluck('migration')->all())->delete();
    }

    private static function migrator(string $table): Migrator
    {
        $repository = new DatabaseMigrationRepository(app('db'), $table);

        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }

        return new Migrator($repository, app('db'), app('files'), app('events'));
    }
}
