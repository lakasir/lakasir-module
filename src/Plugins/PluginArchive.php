<?php

namespace Lakasir\LakasirModule\Plugins;

use ZipArchive;

/**
 * Everything we can learn about a module archive without extracting it.
 */
class PluginArchive
{
    public function __construct(
        public readonly string $path,
        public readonly string $rootPrefix,
        public readonly string $name,
        public readonly string $folder,
        public readonly ?string $author,
        public readonly ?string $version,
        public readonly int $fileCount,
        public readonly int $bytes,
        public readonly string $checksum,
    ) {
    }

    public function serviceProviderClass(): string
    {
        return "Modules\\{$this->folder}\\{$this->folder}ServiceProvider";
    }
}
