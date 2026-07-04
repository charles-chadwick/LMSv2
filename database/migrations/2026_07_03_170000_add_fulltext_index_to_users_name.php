<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert `name` to a STORED generated column and index it for full text.
     * SQLite (test DB) has no FULLTEXT support and keeps the virtual column,
     * so the Searchable trait uses its LIKE fallback there.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('name');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')
                ->storedAs("concat_ws(' ', first_name, last_name)")
                ->after('last_name');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->fullText('name');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropFullText(['name']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('name');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')
                ->virtualAs("concat_ws(' ', first_name, last_name)")
                ->after('last_name');
        });
    }
};
