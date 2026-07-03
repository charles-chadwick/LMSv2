<?php

namespace App\Http\Controllers\Curriculum;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Inertia\Inertia;
use Inertia\Response;

class CurriculumController extends Controller
{
    public function show(Course $course): Response
    {
        $this->authorize('manageContent', $course);

        $course->load('modules.lessons');

        return Inertia::render('Curriculum/Show', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'modules' => $course->modules->map(fn ($module): array => [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'lessons' => $module->lessons->map(fn ($lesson): array => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'slug' => $lesson->slug,
                    'content' => $lesson->content,
                    'duration_minutes' => $lesson->duration_minutes,
                ])->values(),
            ])->values(),
        ]);
    }
}
