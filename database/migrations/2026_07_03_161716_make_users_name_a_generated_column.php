<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rebuild `name` as a virtual column derived from first_name/last_name.
        // concat_ws skips nulls so a missing part never yields a null name.
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('name');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')
                ->virtualAs("concat_ws(' ', first_name, last_name)")
                ->after('last_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('name');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->after('id');
        });
    }
};
