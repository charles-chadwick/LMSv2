<?php

use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Test;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the owner can create a multiple choice question with options', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $test = Test::factory()->for($course)->create();

    $this->actingAs($instructor)
        ->post(route('questions.store', $test), [
            'prompt' => 'What is the capital of France?',
            'type' => QuestionType::MultipleChoice->value,
            'points' => 2,
            'options' => [
                ['text' => 'Paris', 'is_correct' => true],
                ['text' => 'Berlin', 'is_correct' => false],
            ],
        ])
        ->assertRedirect(route('questions.index', $test->id));

    $question = Question::where('prompt', 'What is the capital of France?')->sole();

    expect($question->test_id)->toBe($test->id)
        ->and($question->points)->toBe(2)
        ->and($question->type)->toBe(QuestionType::MultipleChoice)
        ->and($question->position)->toBe(0)
        ->and($question->options)->toHaveCount(2)
        ->and($question->options->firstWhere('text', 'Paris')->is_correct)->toBeTrue();
});

test('a non-gradable question is stored without options', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $test = Test::factory()->for($course)->create();

    $this->actingAs($instructor)
        ->post(route('questions.store', $test), [
            'prompt' => 'Explain photosynthesis.',
            'type' => QuestionType::Essay->value,
            'points' => 5,
        ])
        ->assertRedirect();

    expect(Question::where('prompt', 'Explain photosynthesis.')->sole()->options)->toHaveCount(0);
});

test('an auto-gradable question requires a correct option', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $test = Test::factory()->for($course)->create();

    $this->actingAs($instructor)
        ->post(route('questions.store', $test), [
            'prompt' => 'Pick one',
            'type' => QuestionType::MultipleChoice->value,
            'points' => 1,
            'options' => [
                ['text' => 'A', 'is_correct' => false],
                ['text' => 'B', 'is_correct' => false],
            ],
        ])
        ->assertSessionHasErrors('options');
});

test('creating a question requires a prompt and points', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $test = Test::factory()->for($course)->create();

    $this->actingAs($instructor)
        ->post(route('questions.store', $test), ['prompt' => '', 'type' => QuestionType::Essay->value, 'points' => ''])
        ->assertSessionHasErrors(['prompt', 'points']);
});

test('the owner can update a question and reconcile its options', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $test = Test::factory()->for($course)->create();
    $question = Question::factory()->for($test)->create(['prompt' => 'Old', 'points' => 1]);
    $kept = QuestionOption::factory()->for($question)->correct()->create(['text' => 'Keep me']);
    $removed = QuestionOption::factory()->for($question)->create(['text' => 'Remove me']);

    $this->actingAs($instructor)
        ->put(route('questions.update', $question), [
            'prompt' => 'Renamed',
            'type' => QuestionType::MultipleChoice->value,
            'points' => 3,
            'options' => [
                ['id' => $kept->id, 'text' => 'Kept and edited', 'is_correct' => true],
                ['text' => 'Brand new', 'is_correct' => false],
            ],
        ])
        ->assertRedirect(route('questions.index', $test->id));

    $question->refresh();

    expect($question->prompt)->toBe('Renamed')
        ->and($question->points)->toBe(3)
        ->and($question->options->pluck('text')->all())->toEqualCanonicalizing(['Kept and edited', 'Brand new'])
        ->and($kept->fresh()->text)->toBe('Kept and edited')
        ->and($removed->fresh()->trashed())->toBeTrue();
});

test('the owner can soft-delete a question', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create();
    $test = Test::factory()->for($course)->create();
    $question = Question::factory()->for($test)->create();

    $this->actingAs($instructor)->delete(route('questions.destroy', $question))->assertRedirect();

    expect($question->fresh()->trashed())->toBeTrue();
});

test('a non-owner cannot create a question', function (): void {
    $instructor = User::factory()->instructor()->create();
    $test = Test::factory()->create();

    $this->actingAs($instructor)
        ->post(route('questions.store', $test), [
            'prompt' => 'X',
            'type' => QuestionType::Essay->value,
            'points' => 1,
        ])
        ->assertForbidden();
});

test('a guest is redirected from question creation', function (): void {
    $test = Test::factory()->create();

    $this->post(route('questions.store', $test), [
        'prompt' => 'X',
        'type' => QuestionType::Essay->value,
        'points' => 1,
    ])->assertRedirect(route('login'));
});
