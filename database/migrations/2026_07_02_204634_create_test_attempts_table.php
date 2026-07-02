<?php

use App\Enums\TestAttemptStatus;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Test::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->string('status')->default(TestAttemptStatus::InProgress->value);
            $table->decimal('score', 5, 2)->nullable();
            $table->foreignIdFor(User::class, 'graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['test_id', 'user_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_attempts');
    }
};
