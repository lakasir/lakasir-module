<?php

namespace Lakasir\LakasirModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A module installed from a release archive or the registry.
 *
 * Modules checked out as git submodules are intentionally NOT recorded here:
 * they are developer checkouts, not installs, and writing one would let the
 * installer overwrite a working tree.
 */
class Plugin extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'removed_at' => 'datetime',
    ];

    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_REGISTRY = 'registry';

    public function companyPlugins(): HasMany
    {
        return $this->hasMany(CompanyPlugin::class);
    }

    /**
     * Whether `modules/<folder_name>` is a git checkout rather than an
     * installed release. Such a folder must never be replaced by the installer.
     */
    public function isSubmoduleCheckout(): bool
    {
        $path = base_path('modules/'.$this->folder_name);

        return is_file($path.'/.git') || is_file($path.'/../.git/modules/'.$this->folder_name.'/.git');
    }
}
