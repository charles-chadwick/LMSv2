<?php

namespace App\Http\Controllers;

use App\Actions\SyncQuestionOptions;
use App\Enums\QuestionType;
use App\Http\Requests\Question\StoreQuestionRequest;
use App\Http\Requests\Question\UpdateQuestionRequest;
use App\Models\Question;
use App\Models\Test;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuestionController extends Controller
{
    public function index(Test $test): Response
    {
        $this->authorize('manageContent', $test->course);

        $test->load('questions:id,test_id,prompt,type,points,position');

        return Inertia::render('Questions/Index', [
            'course' => $test->course->only('id', 'title', 'slug'),
            'test' => $test->only('id', 'title'),
            'questions' => $test->questions->map(fn (Question $question): array => [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'type' => $question->type->value,
                'points' => $question->points,
                'position' => $question->position,
            ])->values(),
        ]);
    }

    public function create(Test $test): Response
    {
        $this->authorize('manageContent', $test->course);

        return Inertia::render('Questions/Create', [
            'course' => $test->course->only('id', 'title', 'slug'),
            'test' => $test->only('id', 'title'),
            'questionTypes' => QuestionType::options(),
        ]);
    }

    public function store(StoreQuestionRequest $request, Test $test): RedirectResponse
    {
        $this->authorize('manageContent', $test->course);

        $validated = $request->validated();

        DB::transaction(function () use ($test, $validated): void {
            $question = $test->questions()->create([
                ...Arr::except($validated, 'options'),
                'position' => $test->questions()->count(),
            ]);

            SyncQuestionOptions::run($question, $validated['options'] ?? []);
        });

        return redirect()->route('questions.index', $test->id)->with('status', 'Question created.');
    }

    public function edit(Question $question): Response
    {
        $this->authorize('manageContent', $question->test->course);

        $question->load('options:id,question_id,text,is_correct,position');

        return Inertia::render('Questions/Edit', [
            'course' => $question->test->course->only('id', 'title', 'slug'),
            'test' => $question->test->only('id', 'title'),
            'questionTypes' => QuestionType::options(),
            'question' => [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'type' => $question->type->value,
                'points' => $question->points,
                'options' => $question->options->map(fn ($option): array => [
                    'id' => $option->id,
                    'text' => $option->text,
                    'is_correct' => $option->is_correct,
                ])->values(),
            ],
        ]);
    }

    public function update(UpdateQuestionRequest $request, Question $question): RedirectResponse
    {
        $this->authorize('manageContent', $question->test->course);

        $validated = $request->validated();

        DB::transaction(function () use ($question, $validated): void {
            $question->update(Arr::except($validated, 'options'));

            SyncQuestionOptions::run($question, $validated['options'] ?? []);
        });

        return redirect()->route('questions.index', $question->test_id)->with('status', 'Question updated.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $this->authorize('manageContent', $question->test->course);

        $testId = $question->test_id;
        $question->delete();

        return redirect()->route('questions.index', $testId)->with('status', 'Question deleted.');
    }
}
