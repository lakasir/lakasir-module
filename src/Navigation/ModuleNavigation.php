<?php

namespace Lakasir\LakasirModule\Navigation;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

/**
 * Sidebar entries for installed modules.
 *
 * Panels that build their navigation by hand (instead of relying on Filament's
 * automatic registration) need these groups merged into their own builder:
 *
 *     ->groups([
 *         ...$this->getNavigationGroups(),
 *         ...ModuleNavigation::groups(),
 *     ])
 *
 * Everything is resolved by path rather than by named route: the sidebar can
 * render before the panel finishes registering routes, and `getUrl()` on a
 * resource would throw at that point.
 */
class ModuleNavigation
{
    /**
     * One group per module that exposes Filament resources.
     *
     * @return array<int, NavigationGroup>
     */
    public static function groups(): array
    {
        $groups = [];

        foreach (self::resources() as $module => $resources) {
            $items = [];

            foreach ($resources as $resource) {
                if (! class_exists($resource)) {
                    continue;
                }

                $url = self::panelPath().'/'.$resource::getSlug();

                // Module resources extend ModuleResource, whose getLabel() is
                // null (no single model-derived label); the navigation label is
                // the one the module actually defines.
                $label = method_exists($resource, 'getNavigationLabel')
                    ? ($resource::getNavigationLabel() ?: class_basename($resource))
                    : class_basename($resource);

                $items[] = NavigationItem::make($label)
                    ->visible(fn (): bool => $resource::canAccess())
                    ->icon($resource::getNavigationIcon())
                    ->isActiveWhen(fn (): bool => request()->is(ltrim($url, '/').'*'))
                    ->url($url);
            }

            if ($items !== []) {
                $groups[] = NavigationGroup::make(__(ucfirst($module)))->items($items);
            }
        }

        return $groups;
    }

    /**
     * Entry for the module manager page itself.
     */
    public static function managerItem(): NavigationItem
    {
        $url = self::panelPath().'/'.self::managerSlug();

        return NavigationItem::make(__('Modules'))
            ->icon('heroicon-o-puzzle-piece')
            ->isActiveWhen(fn (): bool => request()->is(ltrim($url, '/').'*'))
            ->url($url);
    }

    public static function managerSlug(): string
    {
        return 'modules-overview';
    }

    /**
     * Installed modules keyed to the resources they expose in the sidebar.
     *
     * Scanned from `modules/<Name>/src/Filament/Resources`, the same directory
     * LakasirModulePlugin registers, so installing a module needs no change
     * anywhere else.
     *
     * @return array<string, array<int, class-string>>
     */
    public static function resources(): array
    {
        if (! is_dir(base_path('modules'))) {
            return [];
        }

        $modules = [];

        foreach (glob(base_path('modules').'/*', GLOB_ONLYDIR) ?: [] as $path) {
            $module = basename($path);
            $resourcesDir = $path.'/src/Filament/Resources';

            if (! is_dir($resourcesDir)) {
                continue;
            }

            $resources = [];

            foreach (glob($resourcesDir.'/*Resource.php') ?: [] as $file) {
                $class = "Modules\\{$module}\\Filament\\Resources\\".basename($file, '.php');

                if (class_exists($class)) {
                    $resources[] = $class;
                }
            }

            if ($resources !== []) {
                $modules[$module] = $resources;
            }
        }

        return $modules;
    }

    /**
     * The active panel's base path, with a leading slash (e.g. "/member").
     *
     * Falls back to the tenant panel so a console call still resolves.
     */
    public static function panelPath(): string
    {
        $panel = Filament::getCurrentPanel() ?? Filament::getPanel('tenant');

        return '/'.trim((string) $panel?->getPath(), '/');
    }
}
