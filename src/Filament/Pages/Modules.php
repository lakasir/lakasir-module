<?php

namespace Lakasir\LakasirModule\Filament\Pages;

use App\Models\Company;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Lakasir\LakasirModule\Models\Plugin;
use Lakasir\LakasirModule\Plugins\MigrationLedger;
use Lakasir\LakasirModule\Plugins\PluginArchiveInspector;
use Lakasir\LakasirModule\Plugins\PluginInstaller;
use Lakasir\LakasirModule\Plugins\PluginRegistry;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Throwable;

class Modules extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $slug = 'modules-overview';

    protected static string $view = 'lakasir-module::filament.pages.modules';

    #[Url]
    public string $tab = 'installed';

    /** Uploaded release archive awaiting installation. */
    public $archive = null;

    /**
     * Registry entries, or null while they have never been fetched. An empty
     * array means "fetched and genuinely empty", which is a different state:
     * conflating the two showed "everything is installed" before the first
     * fetch ever ran.
     *
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $catalog = null;

    public ?string $catalogError = null;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function getNavigationLabel(): string
    {
        return __('Modules');
    }

    public function getTitle(): string
    {
        return __('Modules');
    }

    /**
     * Installed modules, derived from the `modules/` directory the
     * lakasir-module runtime scans.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getModules(): array
    {
        $plugins = Plugin::query()->with('companyPlugins')->get()->keyBy('folder_name');
        $companyId = Company::currentId();
        $registry = collect($this->cachedRegistry())->keyBy('folder');
        $appVersion = $this->appVersion();

        return collect(getModules())
            ->map(function (string $path) use ($plugins, $companyId, $registry, $appVersion): array {
                $folder = basename($path);
                $composer = json_decode(@file_get_contents($path.'/composer.json') ?: '{}', true) ?: [];
                $provider = "Modules\\{$folder}\\{$folder}ServiceProvider";
                $plugin = $plugins->get($folder);
                $version = getModuleVersion($path, $composer['version'] ?? null);
                $git = isModuleDevCheckout($path);
                $entry = $registry->get($folder);
                $registryVersion = is_array($entry) ? ($entry['version'] ?? null) : null;

                return [
                    'name' => $folder,
                    'package' => $composer['name'] ?? '-',
                    'version' => $version,
                    'provider' => $provider,
                    'title' => class_exists($provider) && method_exists($provider, 'getTitle')
                        ? ($provider::getTitle() ?: $folder)
                        : $folder,
                    'has_filament' => is_dir($path.'/src/Filament/Resources'),
                    'source' => $git ? 'git' : ($plugin?->source ?? 'local'),
                    'migrated' => $plugin?->companyPlugins->firstWhere('company_id', $companyId)?->isMigrated() ?? false,
                    'plugin_id' => $plugin?->getKey(),
                    'removed' => $plugin?->removed_at !== null,
                    'removable' => $plugin !== null && ! $git,
                    'registry_version' => $registryVersion,
                    'min_app_version' => is_array($entry) ? ($entry['min_app_version'] ?? null) : null,
                    // A checkout's version is its tag; releasing it is git's job.
                    'update_available' => ! $git
                        && $plugin !== null
                        && is_string($registryVersion)
                        && $this->isNewer($registryVersion, $this->runningVersion($version, $plugin, $composer)),
                    'update_blocked' => $git
                        ? 'git'
                        : ($plugin === null ? 'unmanaged' : $this->blockedByApp($entry, $appVersion)),
                    'rollback_available' => ! $git && is_dir(PluginInstaller::backupPath($folder)),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * The registry index as far as the cache knows it, without a network call.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cachedRegistry(): array
    {
        return app(PluginRegistry::class)->cached() ?? [];
    }

    /**
     * Running version of an installed module: the newer of its files and the
     * version its install recorded. The folder name is what the registry keys
     * on, so the comparison is per release, not per checkout tag.
     */
    private function runningVersion(string $diskVersion, ?Plugin $plugin, array $composer): string
    {
        $installed = $plugin?->version ?: ($composer['version'] ?? null);

        return $this->isNewer((string) $installed, $diskVersion) ? (string) $installed : $diskVersion;
    }

    private function isNewer(string $candidate, string $current): bool
    {
        $normalize = fn (string $version) => ltrim(trim($version), 'vV');

        if (blank($current) || $current === '-') {
            return true;
        }

        return version_compare($normalize($candidate), $normalize($current), '>');
    }

    /**
     * Why a registry release cannot be installed on this app version, or null.
     *
     * `version.txt` is the same source the app updater reads; a missing file or
     * `Development` means a local checkout, where every module is allowed.
     */
    private function blockedByApp(mixed $entry, ?string $appVersion): ?string
    {
        $required = is_array($entry) ? ($entry['min_app_version'] ?? null) : null;

        if (! is_string($required) || $required === '' || $appVersion === null) {
            return null;
        }

        return version_compare($appVersion, ltrim($required, 'vV'), '<') ? 'app' : null;
    }

    private function appVersion(): ?string
    {
        $file = base_path('version.txt');

        if (! is_file($file)) {
            return null;
        }

        $version = trim((string) file_get_contents($file));

        return filled($version) && $version !== 'Development' ? ltrim($version, 'vV') : null;
    }

    /**
     * Plugins whose files were removed but whose metadata and data remain.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRemovedPlugins(): array
    {
        return Plugin::query()
            ->whereNotNull('removed_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Plugin $plugin) => [
                'plugin_id' => $plugin->getKey(),
                'name' => $plugin->name,
                'title' => $plugin->name,
                'version' => $plugin->version,
                'removed_at' => $plugin->removed_at,
            ])
            ->all();
    }

    /**
     * Modules published in the registry that are not installed yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCatalog(): array
    {
        return collect($this->catalog ?? [])
            ->reject(fn (array $module) => is_dir(base_path('modules/'.($module['folder'] ?? ''))))
            ->values()
            ->all();
    }

    public function loadCatalog(): void
    {
        try {
            $this->catalog = app(PluginRegistry::class)->index();
            $this->catalogError = null;
        } catch (Throwable $e) {
            $this->catalog = [];
            $this->catalogError = $e->getMessage();
        }
    }

    /**
     * Fetch the index again, ignoring the cached copy.
     */
    public function refreshRegistry(): void
    {
        try {
            app(PluginRegistry::class)->forget();
        } catch (Throwable) {
            // The next fetch reports the real problem.
        }

        $this->loadCatalog();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;

        if ($tab === 'catalog' && $this->catalog === null && $this->catalogError === null) {
            $this->loadCatalog();
        }
    }

    /**
     * Fetch the registry on first view of the Catalog tab, so it shows the
     * list straight away instead of an "already installed" message.
     *
     * The Installed tab never fetches: opening the page costs no network call,
     * `refreshRegistry` is the only thing that reads the index.
     */
    public function mount(): void
    {
        if ($this->tab === 'catalog' && $this->catalog === null) {
            $this->loadCatalog();
        }
    }

    /**
     * Whether the registry has been read at least once, so the Installed tab
     * knows whether it can compare versions. Never fetches.
     */
    public function registryChecked(): bool
    {
        return app(PluginRegistry::class)->cached() !== null;
    }

    /**
     * Whether a fetch has completed at least once.
     */
    public function catalogLoaded(): bool
    {
        return $this->catalog !== null;
    }

    public function installArchive(PluginInstaller $installer): void
    {
        $this->validate([
            'archive' => ['required', 'file', 'extensions:zip', 'max:'.(int) config('lakasir-module.max_upload_kb', 32768)],
        ]);

        $path = $this->archive->getRealPath();

        try {
            $plugin = $installer->install($path, Plugin::SOURCE_UPLOAD);
            $installer->migrate($plugin);

            Notification::make()
                ->success()
                ->title(__('Module installed'))
                ->body(__(':name :version', ['name' => $plugin->name, 'version' => $plugin->version ?: '']))
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title(__('Installation failed'))
                ->body($e->getMessage())
                ->persistent()
                ->send();
        } finally {
            $this->archive = null;
        }
    }

    public function installFromRegistry(string $name, PluginInstaller $installer): void
    {
        $module = collect($this->catalog)->firstWhere('name', $name);

        if ($module === null) {
            Notification::make()->danger()->title(__('Module not found in the registry'))->send();

            return;
        }

        $module += ['folder' => ''];

        try {
            $registry = app(PluginRegistry::class);
            $path = $registry->download($module);

            try {
                $plugin = $installer->install($path, Plugin::SOURCE_REGISTRY);
            } finally {
                @unlink($path);
            }

            $installer->migrate($plugin);

            Notification::make()
                ->success()
                ->title(__('Module installed'))
                ->body($plugin->name)
                ->send();

            $this->loadCatalog();
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title(__('Installation failed'))
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }

    /**
     * Replace an installed module with the registry's newer release.
     *
     * The files are staged and only swapped once the download, the extract and
     * the module's composer install all succeeded, so a failure leaves the
     * installed tree exactly as it was.
     */
    public function update(int $pluginId, PluginInstaller $installer): void
    {
        $plugin = Plugin::query()->find($pluginId);

        if ($plugin === null) {
            return;
        }

        $module = collect($this->cachedRegistry())->firstWhere('folder', $plugin->folder_name);

        if ($module === null) {
            Notification::make()->danger()->title(__('This module is not in the registry'))->send();

            return;
        }

        try {
            $registry = app(PluginRegistry::class);
            $path = $registry->download($module);

            try {
                $plugin = $installer->update($plugin, $path);
            } finally {
                @unlink($path);
            }

            $installer->migrate($plugin);

            Notification::make()
                ->success()
                ->title(__('Module updated'))
                ->body(__(':name :version', ['name' => $plugin->name, 'version' => $plugin->version ?: '']))
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title(__('Update failed'))
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }

    /**
     * Put the previous files back. The database schema and its data stay as
     * they are; only the files and the recorded version go back.
     */
    public function rollback(int $pluginId, PluginInstaller $installer): void
    {
        $plugin = Plugin::query()->find($pluginId);

        if ($plugin === null) {
            return;
        }

        try {
            $plugin = $installer->rollback($plugin);

            Notification::make()
                ->success()
                ->title(__('Module restored'))
                ->body(__(':name :version', ['name' => $plugin->name, 'version' => $plugin->version ?: '']))
                ->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title(__('Rollback failed'))->body($e->getMessage())->send();
        }
    }

    public function uninstall(int $pluginId, PluginInstaller $installer): void
    {
        $plugin = Plugin::query()->find($pluginId);

        if ($plugin === null) {
            return;
        }

        try {
            $installer->uninstall($plugin);

            Notification::make()
                ->success()
                ->title(__('Module removed'))
                ->body(__('Its data was kept. Reinstall it any time to get the same records back.'))
                ->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title(__('Removal failed'))->body($e->getMessage())->send();
        }
    }

    /**
     * Permanently delete a removed module's data for the current company.
     *
     * The tables are the ones its migrations created, read from the ledger the
     * module wrote, so nothing outside the module is touched.
     */
    public function purge(int $pluginId, PluginInstaller $installer): void
    {
        $plugin = Plugin::query()->find($pluginId);

        if ($plugin === null) {
            return;
        }

        try {
            $tables = $this->tablesCreatedBy($plugin);

            $installer->purge($plugin, $tables);

            Notification::make()
                ->success()
                ->title(__('Module data deleted'))
                ->body($tables === [] ? '' : implode(', ', $tables))
                ->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title(__('Deletion failed'))->body($e->getMessage())->send();
        }
    }

    /**
     * Tables the module's migrations created, taken from the migration files
     * it shipped. Only names the ledger recorded are considered.
     *
     * @return array<int, string>
     */
    private function tablesCreatedBy(Plugin $plugin): array
    {
        $path = base_path("modules/{$plugin->folder_name}/database/migrations");

        if (! is_dir($path)) {
            return [];
        }

        $migrationTable = MigrationLedger::nameFor($plugin->name);
        $ran = Schema::hasTable($migrationTable)
            ? collect(Schema::getConnection()->table($migrationTable)->pluck('migration'))->all()
            : [];

        $tables = [];

        foreach (glob($path.'/*.php') ?: [] as $file) {
            if ($ran !== [] && ! in_array(basename($file, '.php'), $ran, true)) {
                continue;
            }

            $contents = (string) file_get_contents($file);

            if (preg_match_all('/Schema::create\(\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $contents, $matches)) {
                $tables = array_merge($tables, $matches[1]);
            }
        }

        return array_values(array_unique($tables));
    }

    /**
     * Preview of the uploaded archive, shown before anything is written.
     *
     * Resolved lazily so the view can call it without arguments.
     */
    public function inspectArchive(): ?array
    {
        if ($this->archive === null) {
            return null;
        }

        try {
            $archive = app(PluginArchiveInspector::class)->inspect($this->archive->getRealPath());

            return [
                'name' => $archive->name,
                'folder' => $archive->folder,
                'version' => $archive->version,
                'author' => $archive->author,
                'files' => $archive->fileCount,
                'size' => $archive->bytes,
                'conflict' => File::isDirectory(base_path('modules/'.$archive->folder)),
            ];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
