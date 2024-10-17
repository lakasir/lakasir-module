<?php

namespace Lakasir\LakasirModule;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class LakasirModulePlugin implements Plugin
{
    public function getId(): string
    {
        return 'lakasir-module';
    }

    public function register(Panel $panel): void
    {
        $modules = array_map(fn ($module) => $this->loadResourceFromModule($module), $this->loadModules());
        if (! empty($modules)) {
            foreach ($modules as $module) {
                $panel->resources($module);
            }
        }
    }

    public function boot(Panel $panel): void
    {
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    private function loadResourceFromModule($module): array
    {
        $moduleResource = [];
        $resourcesNamespace = "$module\\Filament\\Resources";
        $dir = base_path("modules/$module/src/Filament/Resources");
        $directoryExists = File::isDirectory($dir);
        if (! $directoryExists) {
            return [];
        }
        $resourcesPath = File::directories($dir);
        foreach ($resourcesPath as $path) {
            $path = basename($path);
            if (class_exists("Modules\\$resourcesNamespace\\$path")) {
                $moduleResource[] = "Modules\\$resourcesNamespace\\$path";
            }
        }

        return $moduleResource;
    }

    private function loadModules(): array
    {
        return array_map(fn ($module) => basename($module), File::isDirectory(base_path('modules')) ? File::directories(base_path('modules')) : []);
    }

    public function navigationGroups(): array
    {
        $groups = [];
        foreach ($this->loadModules() as $module) {
            $resources = $this->loadResourceFromModule($module);
            if (count($resources) == 0) {
                $groups[] = NavigationGroup::make($module)->items([]);
            }

            /** @var array<NavigationItem> */
            $navItem = [];
            /** @var \Filament\Resources\Resource */
            foreach ($resources as $resource) {
                $navItem[] = Arr::first($resource::getNavigationItems());
            }
            $groups[] = NavigationGroup::make($module)
                ->items($navItem);
        }

        return $groups;
    }
}
