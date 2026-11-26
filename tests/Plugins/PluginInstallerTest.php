<?php

namespace Lakasir\LakasirModule\Tests\Plugins;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Lakasir\LakasirModule\Models\Plugin;
use Lakasir\LakasirModule\Plugins\PluginInstaller;
use Lakasir\LakasirModule\Plugins\PluginRegistry;
use Lakasir\LakasirModule\Tests\TestCase;
use RuntimeException;
use ZipArchive;

/**
 * Updating a module rewrites `modules/<folder>` in place, so every rule here is
 * about what must survive a failure. No network and no composer: the index and
 * the archives are local files and `composer install` is `/bin/true`.
 */
class PluginInstallerTest extends TestCase
{
    use RefreshDatabase;

    private const FOLDER = 'ZzzUpdateProbe';

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('app/testing-module-update');
        File::deleteDirectory($this->root);
        File::ensureDirectoryExists($this->root);

        config([
            'lakasir-module.composer_binary' => '/bin/true',
            'lakasir-module.registry_url' => 'file://'.$this->root.'/index.json',
            'lakasir-module.registry_cache_ttl' => 600,
        ]);

        Cache::forget('lakasir-module.registry.index');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('modules/'.self::FOLDER));
        File::deleteDirectory(PluginInstaller::backupPath(self::FOLDER));
        File::deleteDirectory(PluginInstaller::stagingPath(self::FOLDER));
        File::deleteDirectory($this->root);

        Cache::forget('lakasir-module.registry.index');

        parent::tearDown();
    }

    public function test_the_registry_index_is_read_once_and_then_cached(): void
    {
        $this->writeIndex('1.0.0');

        $registry = app(PluginRegistry::class);

        $this->assertCount(1, $registry->index());
        $this->assertCount(1, $registry->index());
        $this->assertTrue(Cache::has('lakasir-module.registry.index'));

        unlink($this->root.'/index.json');

        $this->assertCount(1, $registry->index());
    }

    public function test_a_failed_composer_install_leaves_the_installed_tree_untouched(): void
    {
        $this->install('1.0.0');
        $before = $this->treeHash();

        config(['lakasir-module.composer_binary' => '/bin/false']);

        $plugin = Plugin::query()->where('folder_name', self::FOLDER)->firstOrFail();

        try {
            app(PluginInstaller::class)->update($plugin, $this->archive('2.0.0'));
            $this->fail('A failing composer install must abort the update.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame($before, $this->treeHash());
        $this->assertDirectoryDoesNotExist(PluginInstaller::backupPath(self::FOLDER));
        $this->assertDirectoryDoesNotExist(PluginInstaller::stagingPath(self::FOLDER));
    }

    public function test_a_failed_download_leaves_the_installed_tree_untouched(): void
    {
        $this->install('1.0.0');
        $before = $this->treeHash();

        $this->writeIndex('2.0.0', download: $this->root.'/missing.zip');

        $registry = app(PluginRegistry::class);

        try {
            $registry->download($registry->modules()[0]);
            $this->fail('A missing archive must abort the update.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame($before, $this->treeHash());
        $this->assertSame('1.0.0', $this->versionOnDisk());
        $this->assertDirectoryDoesNotExist(PluginInstaller::backupPath(self::FOLDER));
    }

    public function test_an_update_swaps_the_files_in_and_keeps_one_backup_generation(): void
    {
        $this->install('1.0.0');

        $plugin = Plugin::query()->where('folder_name', self::FOLDER)->firstOrFail();
        $plugin = app(PluginInstaller::class)->update($plugin, $this->archive('2.0.0'));

        $this->assertSame('2.0.0', $this->versionOnDisk());
        $this->assertSame('2.0.0', $plugin->version);
        $this->assertSame('1.0.0', $this->versionIn(PluginInstaller::backupPath(self::FOLDER)));

        $plugin = app(PluginInstaller::class)->rollback($plugin);

        $this->assertSame('1.0.0', $this->versionOnDisk());
        $this->assertSame('1.0.0', $plugin->version);
        $this->assertSame('2.0.0', $this->versionIn(PluginInstaller::backupPath(self::FOLDER)));
    }

    public function test_a_git_checkout_is_refused_by_update_and_rollback(): void
    {
        $this->install('1.0.0');

        file_put_contents(base_path('modules/'.self::FOLDER.'/.git'), 'gitdir: ../.git/modules/x');

        $plugin = Plugin::query()->where('folder_name', self::FOLDER)->firstOrFail();
        $installer = app(PluginInstaller::class);

        foreach ([fn () => $installer->update($plugin, $this->archive('2.0.0')), fn () => $installer->rollback($plugin)] as $action) {
            try {
                $action();
                $this->fail('A git checkout must never be replaced.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('is a git checkout', $e->getMessage());
            }
        }

        @unlink(base_path('modules/'.self::FOLDER.'/.git'));
    }

    private function install(string $version): void
    {
        app(PluginInstaller::class)->install($this->archive($version), Plugin::SOURCE_REGISTRY);
    }

    private function archive(string $version): string
    {
        return $this->zip([
            'composer.json' => json_encode([
                'name' => 'lakasir/zzz-update-probe',
                'version' => $version,
                'autoload' => ['psr-4' => ['Modules\\'.self::FOLDER.'\\' => 'src']],
            ]),
            'src/'.self::FOLDER.'ServiceProvider.php' => '<?php',
            'notes.txt' => 'release '.$version,
        ]);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zip(array $files): string
    {
        $path = tempnam($this->root, 'module-').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
    }

    private function writeIndex(string $version, ?string $download = null): void
    {
        file_put_contents($this->root.'/index.json', json_encode([
            'modules' => [[
                'name' => 'lakasir/zzz-update-probe',
                'folder' => self::FOLDER,
                'title' => 'Probe',
                'version' => $version,
                'download' => 'file://'.($download ?? $this->archive($version)),
                'min_app_version' => '0.0.1',
            ]],
        ]));
    }

    private function versionOnDisk(): string
    {
        return $this->versionIn(base_path('modules/'.self::FOLDER));
    }

    private function versionIn(string $path): string
    {
        return json_decode((string) file_get_contents($path.'/composer.json'), true)['version'];
    }

    private function treeHash(): string
    {
        $hashes = [];

        foreach (File::allFiles(base_path('modules/'.self::FOLDER), true) as $file) {
            $hashes[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
        }

        ksort($hashes);

        return hash('sha256', json_encode($hashes));
    }
}
