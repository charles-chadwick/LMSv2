<?php

use App\Enums\QuestionType;
use App\Models\Test;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Test::class)->constrained()->cascadeOnDelete();
            $table->longText('prompt');
            $table->string('type')->default(QuestionType::MultipleChoice->value);
            $table->unsignedInteger('points')->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
