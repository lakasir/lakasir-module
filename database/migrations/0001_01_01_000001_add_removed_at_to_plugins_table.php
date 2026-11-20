<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Removing a module keeps its metadata so a reinstall finds its data
     * again; `removed_at` records that the files are gone while the record
     * stays.
     */
    public function up(): void
    {
        if (! Schema::hasTable('plugins') || Schema::hasColumn('plugins', 'removed_at')) {
            return;
        }

        Schema::table('plugins', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable()->after('checksum');
        });
    }

    public function down(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            $table->dropColumn('removed_at');
        });
    }
};
