<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use Inertia\Inertia;
use Inertia\Response;

class RosterController extends Controller
{
    public function index(Course $course): Response
    {
        $this->authorize('viewRoster', $course);

        $students = $course->enrollments()
            ->with('student:id,name')
            ->latest('enrolled_at')
            ->get()
            ->map(fn (Enrollment $enrollment): array => [
                'id' => $enrollment->id,
                'name' => $enrollment->student->name,
                'status' => $enrollment->status,
                'progress_percentage' => $enrollment->progress_percentage,
                'enrolled_at' => $enrollment->enrolled_at?->toDateString(),
            ]);

        return Inertia::render('Courses/Roster', [
            'course' => [
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'students' => $students,
        ]);
    }
}
