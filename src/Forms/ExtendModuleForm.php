<?php

namespace Lakasir\LakasirModule\Forms;

use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class ExtendModuleForm
{
    public static function fill(array $data, string $form, ?Model $record)
    {
        foreach (getModules() as $module) {
            if (File::isDirectory("{$module}/src/Extends/{$form}")) {
                $basename = basename($module);
                $extendNamespace = "$basename\\Extends\\{$form}";
                $formClass = "Modules\\$extendNamespace\\Form";
                $data = $formClass::setRecord($record)::fill($data);
            }
        }

        return $data;
    }

    public static function make(string $form): array
    {
        $forms = [];
        foreach (getModules() as $module) {
            if (File::isDirectory("{$module}/src/Extends/{$form}")) {
                $basename = basename($module);
                $serviceProvider = "Modules\\$basename\\{$basename}ServiceProvider";
                $extendNamespace = "$basename\\Extends\\{$form}";
                $formClass = "Modules\\$extendNamespace\\Form";
                $statePath = str(getPackageModuleName($basename))->snake('-')->lower()->value();

                $forms[] = Tab::make($formClass::getTitle() ?? $serviceProvider::getTitle() ?? $basename)
                    ->statePath($statePath)
                    ->schema(function ($record) use ($formClass) {
                        return $formClass::make($record);
                    });
            }
        }

        return [
            Tabs::make('module')
                ->columnSpanFull()
                ->schema($forms),
        ];
    }
}
