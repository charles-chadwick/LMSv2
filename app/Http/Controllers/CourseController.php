<?php

namespace App\Http\Controllers;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\UserRole;
use App\Http\Requests\Course\StoreCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /**
     * Courses shown per page on the management index.
     */
    private const PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Course::class);

        $user = $request->user();

        $courses = Course::query()
            ->when(
                ! $user->hasRole(UserRole::Admin->value),
                fn ($query) => $query->where('instructor_id', $user->id),
            )
            ->withSearch($request->query('search'))
            ->withFilters($request->input('filters'))
            ->latest()
            ->paginate(self::PER_PAGE, ['id', 'title', 'slug', 'status', 'level'])
            ->withQueryString();

        return Inertia::render('Courses/Index', [
            'courses' => $courses,
            'filters' => [
                'search' => $request->query('search'),
                ...$request->input('filters', []),
            ],
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Course::class);

        return Inertia::render('Courses/Create', [
            'levels' => CourseLevel::options(),
        ]);
    }

    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $this->authorize('create', Course::class);

        $validated = $request->validated();

        Course::create([
            ...$validated,
            'instructor_id' => $request->user()->id,
            'slug' => $this->uniqueSlug($validated['title']),
            'status' => CourseStatus::Draft,
        ]);

        return redirect()->route('courses.index')->with('status', 'Course created.');
    }

    public function edit(Course $course): Response
    {
        $this->authorize('update', $course);

        return Inertia::render('Courses/Edit', [
            'course' => $course->only('title', 'slug', 'summary', 'description', 'level'),
            'levels' => CourseLevel::options(),
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course): RedirectResponse
    {
        $this->authorize('update', $course);

        $course->update($request->validated());

        return redirect()->route('courses.index')->with('status', 'Course updated.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $this->authorize('delete', $course);

        $course->delete();

        return redirect()->route('courses.index')->with('status', 'Course deleted.');
    }

    /**
     * Build a unique slug for a new course from its title.
     */
    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (Course::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Declarative filter controls for the course list.
     *
     * @return list<array<string, mixed>>
     */
    private function filterOptions(): array
    {
        return [
            [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'multiple' => true,
                'options' => CourseStatus::options(),
            ],
            [
                'key' => 'level',
                'label' => 'Level',
                'type' => 'select',
                'multiple' => true,
                'options' => CourseLevel::options(),
            ],
        ];
    }
}
