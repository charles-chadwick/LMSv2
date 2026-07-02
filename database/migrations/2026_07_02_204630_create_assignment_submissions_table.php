<?php

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Assignment::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->longText('content')->nullable(); // text response; files handled via Media Library
            $table->string('status')->default(SubmissionStatus::Submitted->value);
            $table->unsignedInteger('attempt')->default(1);
            $table->decimal('score', 5, 2)->nullable();
            $table->longText('feedback')->nullable();
            $table->foreignIdFor(User::class, 'graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['assignment_id', 'user_id', 'attempt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
