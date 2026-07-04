<?php

use App\Models\Lesson;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discussions', function (Blueprint $table): void {
            $table->foreignIdFor(Lesson::class)->nullable()->after('course_id')
                ->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('discussions', function (Blueprint $table): void {
            $table->dropConstrainedForeignIdFor(Lesson::class);
        });
    }
};
