<?php

namespace Lakasir\LakasirModule\Plugins;

use Lakasir\LakasirModule\Models\Plugin;
use Illuminate\Database\Schema\Blueprint;
use App\Models\Company;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Installs a module release archive into `modules/`.
 *
 * Only release archives reach this class; git submodule checkouts are refused
 * so a developer's working tree is never overwritten.
 */
class PluginInstaller
{
    public function __construct(private PluginArchiveInspector $inspector)
    {
    }

    /**
     * @param  string  $source  Plugin::SOURCE_UPLOAD or Plugin::SOURCE_REGISTRY
     *
     * @throws RuntimeException
     */
    public function install(string $archivePath, string $source = Plugin::SOURCE_UPLOAD): Plugin
    {
        $archive = $this->inspector->inspect($archivePath);

        $target = base_path('modules/'.$archive->folder);

        if ($this->isGitCheckout($target)) {
            throw new RuntimeException(__('modules/:folder is a git checkout. Update it with git, not the installer.', [
                'folder' => $archive->folder,
            ]));
        }

        $this->extract($archive, $target);

        try {
            $this->composerInstall($target);
        } catch (RuntimeException $e) {
            File::deleteDirectory($target);

            throw $e;
        }

        $plugin = Plugin::query()->updateOrCreate(
            ['folder_name' => $archive->folder],
            [
                'name' => $archive->name,
                'author' => $archive->author,
                'version' => $archive->version,
                'source' => $source,
                'checksum' => $archive->checksum,
                'removed_at' => null,
            ],
        );

        $plugin->companyPlugins()->update(['migrated_at' => null]);

        return $plugin;
    }

    /**
     * Run the module's migrations for the shop in context.
     *
     * Each module keeps its migrations in `migrations_<vendor>_<package>` so it
     * can be rolled back on its own.
     */
    public function migrate(Plugin $plugin): void
    {
        $path = base_path("modules/{$plugin->folder_name}/database/migrations");

        if (! is_dir($path)) {
            return;
        }

        $migrationTable = 'migrations_'.str_replace('/', '_', $plugin->name);
        $companyId = Company::currentId();

        if ($companyId === null) {
            throw new RuntimeException(__('A company context is required to migrate a module.'));
        }

        if (! Schema::hasTable($migrationTable)) {
            $this->createMigrationTable($migrationTable);
        }

        config(['database.migrations' => $migrationTable]);

        $exitCode = Artisan::call('migrate', [
            '--path' => $path,
            '--realpath' => true,
            '--force' => true,
        ]);

        $output = trim(Artisan::output());

        $record = $plugin->companyPlugins()->firstOrNew(['company_id' => $companyId]);
        $record->version = $plugin->version;
        $record->migration_table = $migrationTable;
        $record->migrated_at = $exitCode === 0 ? now() : null;
        $record->save();

        if ($exitCode !== 0) {
            throw new RuntimeException(__('Migration failed for company :company: :output', [
                'company' => $companyId,
                'output' => $output,
            ]));
        }
    }

    /**
     * The same shape `lakasir-module:migrate` creates, so both paths agree.
     */
    private function createMigrationTable(string $table): void
    {
        Schema::create($table, function (Blueprint $schema) {
            $schema->increments('id');
            $schema->string('migration');
            $schema->integer('batch');
        });
    }

    /**
     * Remove a plugin's files, keeping every trace of it in the database.
     *
     * The module's tables and the `migrations_<package>` ledger stay untouched,
     * and the `plugins` row is kept with `removed_at` set so a reinstall finds
     * the same data instead of migrating over it blindly. Deleting data is a
     * separate, explicit action.
     */
    public function uninstall(Plugin $plugin): void
    {
        $target = base_path('modules/'.$plugin->folder_name);

        if ($this->isGitCheckout($target)) {
            throw new RuntimeException(__('modules/:folder is a git checkout; remove it with git.', [
                'folder' => $plugin->folder_name,
            ]));
        }

        File::deleteDirectory($target);

        $plugin->forceFill(['removed_at' => now()])->save();
    }

    /**
     * Delete the module's data for the current company, including the
     * migrations it recorded, so a later install migrates from scratch.
     *
     * @param  array<int, string>  $tables  tables the module created
     */
    public function purge(Plugin $plugin, array $tables): void
    {
        $migrationTable = 'migrations_'.str_replace('/', '_', $plugin->name);

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                Schema::dropIfExists($table);
            }

            Schema::dropIfExists($migrationTable);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $plugin->companyPlugins()->delete();
        $plugin->delete();
    }

    private function extract(PluginArchive $archive, string $target): void
    {
        if (is_dir($target)) {
            File::deleteDirectory($target);
        }

        File::ensureDirectoryExists($target);

        $zip = new ZipArchive();

        if ($zip->open($archive->path) !== true) {
            throw new RuntimeException(__('The archive could not be opened.'));
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);

                if ($entry === false || str_ends_with($entry, '/')) {
                    continue;
                }

                $relative = str_replace('\\', '/', $entry);

                if ($archive->rootPrefix !== '' && str_starts_with($relative, $archive->rootPrefix)) {
                    $relative = substr($relative, strlen($archive->rootPrefix));
                }

                // Defence in depth: the inspector already rejected these.
                if ($relative === '' || str_contains($relative, '..')) {
                    continue;
                }

                $destination = $target.'/'.$relative;
                File::ensureDirectoryExists(dirname($destination));

                $contents = $zip->getFromIndex($i);

                if ($contents === false) {
                    throw new RuntimeException(__('Could not read :file from the archive.', ['file' => $entry]));
                }

                file_put_contents($destination, $contents);
            }
        } finally {
            $zip->close();
        }
    }

    private function composerInstall(string $target): void
    {
        if (! is_file($target.'/composer.json')) {
            return; // Nothing to install; the module ships its own code only.
        }

        $composer = config('lakasir-module.composer_binary') ?: 'composer';

        $process = new Process([$composer, 'install', '--no-interaction', '--no-dev'], $target);
        $process->setTimeout((int) config('lakasir-module.composer_timeout', 300));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(__('composer install failed: :output', [
                'output' => trim($process->getErrorOutput() ?: $process->getOutput()),
            ]));
        }
    }

    private function isGitCheckout(string $path): bool
    {
        return is_dir($path) && is_file($path.'/.git');
    }
}
