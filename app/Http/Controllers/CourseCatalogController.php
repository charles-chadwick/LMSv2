<?php

namespace App\Http\Controllers;

use App\Enums\CourseStatus;
use App\Models\Course;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseCatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $enrolled_course_ids = $request->user()->enrollments()->pluck('course_id');

        $courses = Course::query()
            ->where('status', CourseStatus::Published)
            ->with('instructor:id,name')
            ->latest('published_at')
            ->get()
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'summary' => $course->summary,
                'level' => $course->level,
                'instructor' => $course->instructor->name,
                'is_enrolled' => $enrolled_course_ids->contains($course->id),
            ]);

        return Inertia::render('Catalog/Index', [
            'courses' => $courses,
        ]);
    }

    public function show(Request $request, Course $course): Response
    {
        abort_unless($course->status === CourseStatus::Published, 404);

        $course->load(['instructor:id,name', 'modules.lessons']);

        return Inertia::render('Catalog/Show', [
            'course' => [
                'title' => $course->title,
                'slug' => $course->slug,
                'summary' => $course->summary,
                'description' => $course->description,
                'level' => $course->level,
                'instructor' => $course->instructor->name,
                'modules' => $course->modules->map(fn ($module): array => [
                    'title' => $module->title,
                    'lessons' => $module->lessons->map(fn ($lesson): array => [
                        'title' => $lesson->title,
                    ])->values(),
                ])->values(),
            ],
            'is_enrolled' => $request->user()->enrollments()->where('course_id', $course->id)->exists(),
        ]);
    }
}
