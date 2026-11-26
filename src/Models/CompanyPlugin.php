<?php

namespace Lakasir\LakasirModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-company installation state of a plugin.
 *
 * A module's migrations belong to the shop that installed it, so "migrated" is
 * a property of the pair rather than of the plugin.
 */
class CompanyPlugin extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'migrated_at' => 'datetime',
    ];

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    public function isMigrated(): bool
    {
        return $this->migrated_at !== null;
    }
}
