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
        // Note: `deleted_at` already exists on `users` (added by the base
        // 0001_01_01_000000_create_users_table migration), so only
        // `created_by` needs to be added here.
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->after('id')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
