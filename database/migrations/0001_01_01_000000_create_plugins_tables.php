<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plugins')) {
            return;
        }

        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('folder_name')->unique();
            $table->string('author')->nullable();
            $table->string('version')->nullable();
            $table->string('source')->default('upload');
            $table->string('checksum')->nullable();
            $table->string('repository')->nullable();
            $table->timestamps();
        });

        Schema::create('company_plugins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->foreignId('plugin_id')->constrained()->cascadeOnDelete();
            $table->string('version')->nullable();
            $table->string('migration_table')->nullable();
            $table->timestamp('migrated_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'plugin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_plugins');
        Schema::dropIfExists('plugins');
    }
};
