<?php

namespace Lakasir\LakasirModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-tenant installation state of a plugin.
 *
 * Module migrations run inside each tenant database, so "migrated" is a
 * property of the pair, not of the plugin.
 */
class TenantPlugin extends Model
{
    protected $guarded = ['id'];

    /** Central, like the plugin it belongs to. */
    protected $connection = 'mysql';

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
