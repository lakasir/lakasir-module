<?php

namespace Lakasir\LakasirModule\Console\Commands;

use Illuminate\Support\Facades\File;

trait UpdateNamespaceTrait
{
    protected function updateNamespace($filePath, $newNamespace)
    {
        if (File::exists($filePath)) {
            $fileContents = File::get($filePath);
            $updatedContents = preg_replace('/namespace\s+[\w\\\]+;/', "namespace {$newNamespace};", $fileContents);
            File::put($filePath, $updatedContents);
        } else {
            $this->error("File '{$filePath}' not found for namespace update.");
        }
    }
}
