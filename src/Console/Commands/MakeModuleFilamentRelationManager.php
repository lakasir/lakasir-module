<?php

namespace Lakasir\LakasirModule\Console\Commands;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Commands\Concerns\CanIndentStrings;
use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'lakasir-module:resource-relation-manager')]
class MakeModuleFilamentRelationManager extends Command
{
    use CanIndentStrings;
    use CanManipulateFiles;

    protected $description = 'Create a new Filament relation manager class for a resource in a module';

    protected $signature = 'lakasir-module:resource-relation-manager {module?} {resource?} {relationship?} {recordTitleAttribute?} {--attach} {--associate} {--soft-deletes} {--view} {--panel=} {--F|force}';

    public function handle(): int
    {
        // Get module name from argument or prompt
        $module = Str::studly($this->argument('module') ?? text(
            label: 'Which module should this resource be added to?',
            placeholder: 'Accounting',
            required: true
        ));

        $modulePath = base_path("modules/{$module}");

        // Validate module existence
        if (! File::exists($modulePath)) {
            $this->error("Module '{$module}' does not exist.");

            return static::INVALID;
        }

        // Get resource name from argument or prompt
        $resource = (string) str(
            $this->argument('resource') ?? text(
                label: 'What is the resource you would like to create this in?',
                placeholder: 'DepartmentResource',
                required: true,
            )
        )
            ->studly()
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\');

        // Ensure the resource name ends with "Resource"
        if (! str($resource)->endsWith('Resource')) {
            $resource .= 'Resource';
        }

        // Get relationship from argument or prompt
        $relationship = (string) str($this->argument('relationship') ?? text(
            label: 'What is the relationship?',
            placeholder: 'members',
            required: true,
        ))
            ->trim(' ');

        // Define the relation manager class name
        $managerClass = (string) str($relationship)
            ->studly()
            ->append('RelationManager');

        // Get the record title attribute from argument or prompt
        $recordTitleAttribute = (string) str($this->argument('recordTitleAttribute') ?? text(
            label: 'What is the title attribute?',
            placeholder: 'name',
            required: true,
        ))
            ->trim(' ');

        // Handle the panel selection logic
        $panel = $this->option('panel');

        if ($panel) {
            $panel = Filament::getPanel($panel, isStrict: false);
        }

        if (! $panel) {
            $panels = Filament::getPanels();

            /** @var Panel $panel */
            $panel = (count($panels) > 1) ? $panels[select(
                label: 'Which panel would you like to create this in?',
                options: array_map(
                    fn (Panel $panel): string => $panel->getId(),
                    $panels,
                ),
                default: Filament::getDefaultPanel()->getId()
            )] : Arr::first($panels);
        }

        // Handle resource directories and namespaces from the panel
        $resourceDirectories = $panel->getResourceDirectories();
        $resourceNamespaces = $panel->getResourceNamespaces();

        $resourceNamespace = "Modules\\{$module}\\Filament\\Resources";

        // Handle resource path for module-specific setup
        $resourcePath = (count($resourceDirectories) > 1) ?
            $resourceDirectories[array_search($resourceNamespace, $resourceNamespaces)] :
            ("modules/{$module}/src/Filament/Resources/");

        // Construct the file path
        $path = (string) str($managerClass)
            ->prepend("{$resourcePath}/{$resource}/RelationManagers/")
            ->replace('\\', '/')
            ->append('.php');

        // Check if the file already exists
        if (! $this->option('force') && $this->checkForCollision([$path])) {
            return static::INVALID;
        }

        // Build table actions
        $tableHeaderActions = [
            'Tables\Actions\CreateAction::make(),',
        ];

        if ($this->option('associate')) {
            $tableHeaderActions[] = 'Tables\Actions\AssociateAction::make(),';
        }

        if ($this->option('attach')) {
            $tableHeaderActions[] = 'Tables\Actions\AttachAction::make(),';
        }

        $tableHeaderActions = implode(PHP_EOL, $tableHeaderActions);

        // Define table actions
        $tableActions = [];

        if ($this->option('view')) {
            $tableActions[] = 'Tables\Actions\ViewAction::make(),';
        }

        $tableActions[] = 'Tables\Actions\EditAction::make(),';

        if ($this->option('associate')) {
            $tableActions[] = 'Tables\Actions\DissociateAction::make(),';
        }

        if ($this->option('attach')) {
            $tableActions[] = 'Tables\Actions\DetachAction::make(),';
        }

        $tableActions[] = 'Tables\Actions\DeleteAction::make(),';

        if ($this->option('soft-deletes')) {
            $tableActions[] = 'Tables\Actions\ForceDeleteAction::make(),';
            $tableActions[] = 'Tables\Actions\RestoreAction::make(),';
        }

        $tableActions = implode(PHP_EOL, $tableActions);

        // Handle bulk actions
        $tableBulkActions = [];

        if ($this->option('associate')) {
            $tableBulkActions[] = 'Tables\Actions\DissociateBulkAction::make(),';
        }

        if ($this->option('attach')) {
            $tableBulkActions[] = 'Tables\Actions\DetachBulkAction::make(),';
        }

        $tableBulkActions[] = 'Tables\Actions\DeleteBulkAction::make(),';

        $modifyQueryUsing = '';

        if ($this->option('soft-deletes')) {
            $modifyQueryUsing .= '->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([';
            $modifyQueryUsing .= PHP_EOL.'    SoftDeletingScope::class,';
            $modifyQueryUsing .= PHP_EOL.']))';

            $tableBulkActions[] = 'Tables\Actions\ForceDeleteBulkAction::make(),';
            $tableBulkActions[] = 'Tables\Actions\RestoreBulkAction::make(),';
        }

        $tableBulkActions = implode(PHP_EOL, $tableBulkActions);

        // Copy stub to app
        $this->copyStubToApp('RelationManager', $path, [
            'modifyQueryUsing' => filled($modifyQueryUsing) ? PHP_EOL.$this->indentString($modifyQueryUsing, 3) : $modifyQueryUsing,
            'namespace' => "{$resourceNamespace}\\{$resource}\\RelationManagers",
            'managerClass' => $managerClass,
            'recordTitleAttribute' => $recordTitleAttribute,
            'relationship' => $relationship,
            'tableActions' => $this->indentString($tableActions, 4),
            'tableBulkActions' => $this->indentString($tableBulkActions, 5),
            'tableFilters' => $this->indentString(
                $this->option('soft-deletes') ? 'Tables\Filters\TrashedFilter::make()' : '//',
                4,
            ),
            'tableHeaderActions' => $this->indentString($tableHeaderActions, 4),
        ]);

        $this->components->info("Filament relation manager [{$path}] created successfully in module [{$module}].");

        $this->components->info("Make sure to register the relation in `{$resource}::getRelations()`.");

        return static::SUCCESS;
    }

    protected function getDefaultStubPath(): string
    {
        return base_path('vendor/lakasir/lakasir-module/stubs/filament');
    }
}
