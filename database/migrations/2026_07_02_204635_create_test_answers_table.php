<?php

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\TestAttempt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(TestAttempt::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Question::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(QuestionOption::class)->nullable()->constrained()->nullOnDelete(); // chosen option for MC/TF
            $table->longText('answer_text')->nullable(); // free-text for short_answer/essay
            $table->decimal('points_awarded', 5, 2)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['test_attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_answers');
    }
};
