<?php

namespace Lakasir\LakasirModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

class ModuleMakeCommand extends Command
{
    protected $signature = 'lakasir-module:make';

    protected $description = 'Create a new module and update files and composer.json accordingly';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $moduleName = Str::studly(text(
            label: 'What module should be created?',
            placeholder: 'Accounting',
            required: true
        ));
        $moduleLowerCase = Str::snake($moduleName);

        $stubPath = __DIR__.'/../../../stubs/module-stubs';
        $modulePath = base_path("modules/{$moduleName}");

        if (! File::exists($modulePath)) {
            File::makeDirectory($modulePath, 0755, true);
        }

        $this->copyAndRenameStubFiles($stubPath, $modulePath, $moduleName, $moduleLowerCase);

        $this->updateModuleComposerJson($modulePath, $moduleName);

        $this->info("Module '{$moduleName}' created successfully!");
    }

    protected function copyAndRenameStubFiles($stubPath, $modulePath, $moduleName, $moduleLowerCase)
    {
        $files = File::allFiles($stubPath);

        foreach ($files as $file) {
            $content = File::get($file);
            $targetFilePath = str_replace('sample-module', str($moduleName)->snake('-'), $file->getRelativePathname());
            $targetFilePath = str_replace('SampleModule', $moduleName, $targetFilePath);
            $destinationPath = "{$modulePath}/{$targetFilePath}";
            File::ensureDirectoryExists(dirname($destinationPath));

            $newContent = str_replace('SampleModule', $moduleName, $content);
            $newContent = str_replace('sample_module', $moduleLowerCase, $newContent);
            File::put($destinationPath, $newContent);
        }
    }

    protected function updateModuleComposerJson($modulePath, $moduleName)
    {
        $authorName = $this->ask('Author name', 'Anonymous');
        $authorEmail = $this->ask('Author email', 'example@example.com');
        $authorRole = $this->ask('Author role', 'Developer');

        $composerJsonPath = "{$modulePath}/composer.json";

        $composerContent = File::get($composerJsonPath);

        $composerContent = $this->cleanComposerJson($composerContent);

        $composerJson = json_decode($composerContent, true);

        $composerJson['name'] = Str::of($moduleName)->slug()->prepend('lakasir/');

        $composerJson['autoload']['psr-4']["Modules\\{$moduleName}\\"] = 'src';
        $composerJson['autoload']['psr-4']["Modules\\{$moduleName}\\Database\\Factories\\"] = 'database/factories';
        $composerJson['autoload']['psr-4']["Modules\\{$moduleName}\\Database\\Seeders\\"] = 'database/seeders';

        $composerJson['authors'] = [
            [
                'name' => $authorName,
                'email' => $authorEmail,
                'role' => $authorRole,
            ],
        ];

        File::put($composerJsonPath, json_encode($composerJson, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        $this->info('Module composer.json updated with authors successfully!');

    }

    protected function cleanComposerJson($content)
    {
        $content = str_replace('\\n', "\n", $content);

        $content = preg_replace('/}\s*{/', '},{', $content);

        return trim($content);
    }
}
