<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            // Preserve an index on module_id so the foreign key stays backed
            // once the composite unique that currently covers it is dropped.
            $table->index('module_id');
            $table->dropUnique(['module_id', 'slug']);
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->unique(['module_id', 'slug']);
            $table->dropIndex(['module_id']);
        });
    }
};
