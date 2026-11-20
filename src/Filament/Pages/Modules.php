<?php

namespace Lakasir\LakasirModule\Filament\Pages;

use Lakasir\LakasirModule\Models\Plugin;
use Lakasir\LakasirModule\Plugins\PluginArchiveInspector;
use Lakasir\LakasirModule\Plugins\PluginInstaller;
use Lakasir\LakasirModule\Plugins\PluginRegistry;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
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
     * @return array<int, array{name: string, package: string, title: string, version: string, provider: string, has_filament: bool, source: string, migrated: bool}>
     */
    public function getModules(): array
    {
        $plugins = Plugin::query()->with('tenantPlugins')->get()->keyBy('folder_name');
        $tenantId = tenant()?->getKey();

        return collect(getModules())
            ->map(function (string $path) use ($plugins, $tenantId): array {
                $folder = basename($path);
                $composer = json_decode(@file_get_contents($path.'/composer.json') ?: '{}', true) ?: [];
                $provider = "Modules\\{$folder}\\{$folder}ServiceProvider";
                $plugin = $plugins->get($folder);

                return [
                    'name' => $folder,
                    'package' => $composer['name'] ?? '-',
                    'version' => $composer['version'] ?? '-',
                    'provider' => $provider,
                    'title' => class_exists($provider) && method_exists($provider, 'getTitle')
                        ? ($provider::getTitle() ?: $folder)
                        : $folder,
                    'has_filament' => is_dir($path.'/src/Filament/Resources'),
                    'source' => is_dir($path.'/.git') ? 'git' : ($plugin?->source ?? 'local'),
                    'migrated' => $plugin?->tenantPlugins->firstWhere('tenant_id', $tenantId)?->isMigrated() ?? false,
                    'plugin_id' => $plugin?->getKey(),
                    'removed' => $plugin?->removed_at !== null,
                    'removable' => $plugin !== null && ! is_dir($path.'/.git'),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
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
            $this->catalog = app(PluginRegistry::class)->modules();
            $this->catalogError = null;
        } catch (Throwable $e) {
            $this->catalog = [];
            $this->catalogError = $e->getMessage();
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;

        if ($tab === 'catalog' && $this->catalog === null && $this->catalogError === null) {
            $this->loadCatalog();
        }
    }

    /**
     * Fetch the registry on first view, so opening the Catalog tab shows the
     * list straight away instead of an "already installed" message.
     */
    public function mount(): void
    {
        if ($this->tab === 'catalog' && $this->catalog === null) {
            $this->loadCatalog();
        }
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
     * Permanently delete a removed module's data for the current tenant.
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

        $migrationTable = 'migrations_'.str_replace('/', '_', $plugin->name);
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
