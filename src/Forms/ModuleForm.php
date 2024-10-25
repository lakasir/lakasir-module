<?php

namespace Lakasir\LakasirModule\Forms;

class ModuleForm
{
    protected static ?string $title = null;

    protected static ?string $description = null;

    protected static $record = null;

    public static function setRecord(mixed $record)
    {
        static::$record = $record;

        return static::class;
    }

    public static function getTitle(): ?string
    {
        return static::$title ?? null;
    }

    public static function getDescription(): ?string
    {
        return static::$description ?? null;
    }
}
