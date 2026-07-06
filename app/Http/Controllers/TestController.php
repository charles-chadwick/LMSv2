<?php

namespace App\Http\Controllers;

use App\Http\Requests\Test\StoreTestRequest;
use App\Http\Requests\Test\UpdateTestRequest;
use App\Models\Course;
use App\Models\Test;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TestController extends Controller
{
    public function index(Course $course): Response
    {
        $this->authorize('manageContent', $course);

        $course->load('tests:id,course_id,lesson_id,title,max_attempts,passing_score,due_at');

        return Inertia::render('Tests/Index', [
            'course' => $course->only('id', 'title', 'slug'),
            'tests' => $course->tests->map(fn (Test $test): array => [
                'id' => $test->id,
                'title' => $test->title,
                'max_attempts' => $test->max_attempts,
                'passing_score' => $test->passing_score,
                'due_at' => $test->due_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function create(Course $course): Response
    {
        $this->authorize('manageContent', $course);

        return Inertia::render('Tests/Create', [
            'course' => $course->only('id', 'title', 'slug'),
            'lessons' => $this->lessonOptions($course),
        ]);
    }

    public function store(StoreTestRequest $request, Course $course): RedirectResponse
    {
        $this->authorize('manageContent', $course);

        $course->tests()->create($request->validated());

        return redirect()->route('tests.index', $course->slug)->with('status', 'Test created.');
    }

    public function edit(Test $test): Response
    {
        $this->authorize('manageContent', $test->course);

        return Inertia::render('Tests/Edit', [
            'course' => $test->course->only('id', 'title', 'slug'),
            'lessons' => $this->lessonOptions($test->course),
            'test' => $test->only(
                'id',
                'title',
                'description',
                'lesson_id',
                'time_limit_minutes',
                'max_attempts',
                'passing_score',
                'available_from',
                'due_at',
            ),
        ]);
    }

    public function update(UpdateTestRequest $request, Test $test): RedirectResponse
    {
        $this->authorize('manageContent', $test->course);

        $test->update($request->validated());

        return redirect()->route('tests.index', $test->course->slug)->with('status', 'Test updated.');
    }

    public function destroy(Test $test): RedirectResponse
    {
        $this->authorize('manageContent', $test->course);

        $course = $test->course;
        $test->delete();

        return redirect()->route('tests.index', $course->slug)->with('status', 'Test deleted.');
    }

    /**
     * Selectable lessons for the course, scoped so a test only attaches to a
     * lesson within its own course.
     *
     * @return list<array{value: int, label: string}>
     */
    private function lessonOptions(Course $course): array
    {
        return $course->modules()
            ->with('lessons:id,module_id,title')
            ->get()
            ->flatMap(fn ($module) => $module->lessons->map(fn ($lesson): array => [
                'value' => $lesson->id,
                'label' => $lesson->title,
            ]))
            ->values()
            ->all();
    }
}
