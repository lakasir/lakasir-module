<?php

namespace Lakasir\LakasirModule\Plugins;

use App\Models\Company;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Lakasir\LakasirModule\Models\Plugin;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Installs and updates a module release archive into `modules/`.
 *
 * Only release archives reach this class; git submodule checkouts are refused
 * so a developer's working tree is never overwritten. Every write is staged:
 * the archive is extracted and composer-installed in `storage/app/module-staging`
 * first, and only then swapped into `modules/` with the previous tree kept in
 * `storage/app/module-backups` for one rollback.
 */
class PluginInstaller
{
    public function __construct(private PluginArchiveInspector $inspector) {}

    /**
     * @param  string  $source  Plugin::SOURCE_UPLOAD or Plugin::SOURCE_REGISTRY
     *
     * @throws RuntimeException
     */
    public function install(string $archivePath, string $source = Plugin::SOURCE_UPLOAD): Plugin
    {
        return $this->place($this->inspector->inspect($archivePath), $source);
    }

    /**
     * Replace an installed module with the archive, keeping its previous files
     * for one rollback.
     *
     * @throws RuntimeException
     */
    public function update(Plugin $plugin, string $archivePath): Plugin
    {
        $archive = $this->inspector->inspect($archivePath);

        if ($archive->folder !== $plugin->folder_name) {
            throw new RuntimeException(__('The archive holds :archive, not :folder.', [
                'archive' => $archive->folder,
                'folder' => $plugin->folder_name,
            ]));
        }

        return $this->place($archive, $plugin->source ?: Plugin::SOURCE_REGISTRY);
    }

    /**
     * Put the previous files back, keeping the current ones: the two trees
     * swap places so a rollback is itself reversible. The schema is not
     * touched, only the files and the recorded version.
     *
     * @throws RuntimeException
     */
    public function rollback(Plugin $plugin): Plugin
    {
        $target = base_path('modules/'.$plugin->folder_name);

        $this->assertReplaceable($plugin->folder_name);

        $backup = self::backupPath($plugin->folder_name);

        if (! is_dir($backup)) {
            throw new RuntimeException(__('No previous version of :folder is kept.', [
                'folder' => $plugin->folder_name,
            ]));
        }

        $parking = self::stagingPath($plugin->folder_name);
        File::deleteDirectory($parking);
        File::ensureDirectoryExists(dirname($parking));

        if (! File::moveDirectory($target, $parking)) {
            throw new RuntimeException(__('Could not move modules/:folder aside.', [
                'folder' => $plugin->folder_name,
            ]));
        }

        if (! File::moveDirectory($backup, $target)) {
            File::moveDirectory($parking, $target);

            throw new RuntimeException(__('Could not restore modules/:folder.', [
                'folder' => $plugin->folder_name,
            ]));
        }

        if (! File::moveDirectory($parking, $backup)) {
            throw new RuntimeException(__('Could not keep the current modules/:folder as a backup.', [
                'folder' => $plugin->folder_name,
            ]));
        }

        $plugin->forceFill(['version' => $this->installedVersion($target) ?: $plugin->version])->save();

        return $plugin;
    }

    /**
     * Where the previous files of a module are kept.
     */
    public static function backupPath(string $folder): string
    {
        return storage_path('app/module-backups/'.$folder);
    }

    /**
     * Where a module is assembled before it replaces the installed tree.
     */
    public static function stagingPath(string $folder): string
    {
        return storage_path('app/module-staging/'.$folder);
    }

    /**
     * Run the module's migrations once, for every company.
     *
     * Each module keeps its migrations in `migrations_<vendor>_<package>` so it
     * can be rolled back on its own; that ledger is shared by every company,
     * so "migrated" is stamped on all of them rather than on whichever shop
     * happened to be in context.
     */
    public function migrate(Plugin $plugin): void
    {
        $path = base_path("modules/{$plugin->folder_name}/database/migrations");

        if (! is_dir($path)) {
            $this->recordMigration($plugin, null, now());

            return;
        }

        $migrationTable = MigrationLedger::nameFor($plugin->name);

        MigrationLedger::adopt($migrationTable, $path);

        try {
            MigrationLedger::run($migrationTable, $path);
        } catch (Throwable $e) {
            $this->recordMigration($plugin, $migrationTable, null);

            throw new RuntimeException(__('Migration failed for :name: :output', [
                'name' => $plugin->name,
                'output' => $e->getMessage(),
            ]), 0, $e);
        }

        $this->recordMigration($plugin, $migrationTable, now());
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
        $this->assertReplaceable($plugin->folder_name);

        File::deleteDirectory(base_path('modules/'.$plugin->folder_name));

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
        $migrationTable = MigrationLedger::nameFor($plugin->name);

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

    private function place(PluginArchive $archive, string $source): Plugin
    {
        $target = base_path('modules/'.$archive->folder);

        $this->assertReplaceable($archive->folder);

        $staging = self::stagingPath($archive->folder);

        File::deleteDirectory($staging);
        File::ensureDirectoryExists($staging);

        try {
            $this->extract($archive, $staging);
            $this->composerInstall($staging);
        } catch (Throwable $e) {
            File::deleteDirectory($staging);

            throw $e;
        }

        $this->swap($staging, $target, $archive->folder);

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
     * Move the staged tree into `modules/`, keeping the previous one as the
     * single backup generation.
     */
    private function swap(string $staging, string $target, string $folder): void
    {
        $backup = self::backupPath($folder);

        File::deleteDirectory($backup);
        File::ensureDirectoryExists(dirname($backup));
        File::ensureDirectoryExists(dirname($target));

        $hadPrevious = is_dir($target);

        if ($hadPrevious && ! File::moveDirectory($target, $backup)) {
            throw new RuntimeException(__('Could not move the installed modules/:folder aside.', [
                'folder' => $folder,
            ]));
        }

        if (! File::moveDirectory($staging, $target)) {
            if ($hadPrevious) {
                File::moveDirectory($backup, $target);
            }

            throw new RuntimeException(__('Could not move the new modules/:folder into place.', [
                'folder' => $folder,
            ]));
        }
    }

    /**
     * Refuse to touch a developer checkout: a submodule, a clone or a symlink
     * into one. `modules/Warehouse` can be a checkout while its `plugins` row
     * still says "registry", so the tree itself decides, never the row.
     */
    private function assertReplaceable(string $folder): void
    {
        if ($this->isGitCheckout(base_path('modules/'.$folder))) {
            throw new RuntimeException(__('modules/:folder is a git checkout. Update it with git, not the installer.', [
                'folder' => $folder,
            ]));
        }
    }

    private function recordMigration(Plugin $plugin, string $table, ?\DateTimeInterface $migratedAt): void
    {
        foreach (Company::query()->pluck('id') as $companyId) {
            $record = $plugin->companyPlugins()->firstOrNew(['company_id' => $companyId]);
            $record->version = $plugin->version;
            $record->migration_table = $table;
            $record->migrated_at = $migratedAt;
            $record->save();
        }
    }

    /**
     * The version the files on disk declare, used to keep `plugins.version` in
     * step after a rollback.
     */
    private function installedVersion(string $path): ?string
    {
        $manifest = json_decode(@file_get_contents($path.'/composer.json') ?: '{}', true) ?: [];

        return is_string($manifest['version'] ?? null) && $manifest['version'] !== ''
            ? $manifest['version']
            : null;
    }

    private function extract(PluginArchive $archive, string $target): void
    {
        $zip = new ZipArchive;

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
        return isModuleDevCheckout($path);
    }
}
