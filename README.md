# Very short description of the package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lakasir/lakasir-module.svg?style=flat-square)](https://packagist.org/packages/lakasir/lakasir-module)
[![Total Downloads](https://img.shields.io/packagist/dt/lakasir/lakasir-module.svg?style=flat-square)](https://packagist.org/packages/lakasir/lakasir-module)
![GitHub Actions](https://github.com/lakasir/lakasir-module/actions/workflows/main.yml/badge.svg)

Module runtime for Lakasir: discover, install, update and roll back modules
living in `modules/`, and expose each module's Filament pages, resources,
forms and views to the host panel.

## Requirements

- PHP 8.3+
- Laravel 11
- Filament 3
- `ext-zip`

## Installation

```bash
composer require lakasir/lakasir-module
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag=lakasir-module-config
```

Register the Filament plugin in your panel provider:

```php
use Lakasir\LakasirModule\LakasirModulePlugin;

->plugin(LakasirModulePlugin::make())
```

The plugin registers the **Modules** page so every application can manage its
modules without wiring a page of its own.

## Usage

### Manage modules from the panel

The Modules page lists installed modules, installs a release archive (`.zip`),
installs from the registry, updates to the newest registry version and rolls
back one step. Installed modules are recorded in the `plugins` table, per
company.

### Create a module

```bash
php artisan lakasir-module:make
```

Scaffolds `modules/<Name>` from `stubs/module-stubs`. Generated code lives in
the module's own namespace (`Modules\<Name>`), so the host application stays
untouched.

### Generate module classes

```bash
php artisan lakasir-module:model
php artisan lakasir-module:controller
php artisan lakasir-module:migration
php artisan lakasir-module:resource
php artisan lakasir-module:resource-relation-manager
```

### Run module migrations

```bash
php artisan lakasir-module:migrate {module?} [--rollback] [--refresh]
```

Runs the module's migrations through its own ledger, so a module's migrations
are tracked separately from the host application's.

### Navigation

Panels that build navigation by hand can merge installed module groups:

```php
use Lakasir\LakasirModule\Navigation\ModuleNavigation;

->groups([
    ...$this->getNavigationGroups(),
    ...ModuleNavigation::groups(),
])
```

### Forms and page hooks

Modules extend host forms and pages by convention:

- `modules/<Name>/src/Extends/<Form>/Form.php` — merged by `ExtendModuleForm`
- `Modules\<Name>\Providers\ExtendModuleServiceProvider` — config, views and
  translations for the module
- `Lakasir\LakasirModule\Abstracts\ModuleResource` — resource with a
  `modules/<module>/<resource>` slug
- `Lakasir\LakasirModule\Forms\ModuleForm` — base class for module forms with
  title, description and record

### Registry

`registry_url` points to a JSON index (default: the
[module registry](https://github.com/lakasir/module-registry)). Each read is
cached for `registry_cache_ttl`; the page's Refresh button drops the cache.
Set `registry_url` to `null` to disable installing from the registry.

### Configuration

| Key | Env | Default |
| --- | --- | --- |
| `registry_url` | `LAKASIR_MODULE_REGISTRY_URL` | lakasir module-registry index |
| `timeout` | `LAKASIR_MODULE_REGISTRY_TIMEOUT` | `15` |
| `registry_cache_ttl` | `LAKASIR_MODULE_REGISTRY_CACHE_TTL` | `3600` |
| `max_upload_kb` | `LAKASIR_MODULE_MAX_UPLOAD_KB` | `32768` |
| `composer_binary` | `LAKASIR_MODULE_COMPOSER_BINARY` | auto-detected |
| `composer_timeout` | `LAKASIR_MODULE_COMPOSER_TIMEOUT` | `300` |

### Testing

```bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email lakasirapp@gmail.com instead of using the issue tracker.

## Credits

-   [Lakasir](https://github.com/lakasir)
-   [All Contributors](../../contributors)

## License

The GNU GPLv3. Please see [License File](LICENSE.md) for more information.
