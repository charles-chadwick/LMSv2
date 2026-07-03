<?php

namespace App\Http\Controllers;

use App\Actions\EnrollStudent;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentController extends Controller
{
    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('enroll', $course);

        EnrollStudent::run($request->user(), $course);

        return back()->with('status', 'Enrolled.');
    }

    public function index(Request $request): Response
    {
        $enrollments = $request->user()->enrollments()
            ->with('course:id,title,slug')
            ->latest('enrolled_at')
            ->get()
            ->map(fn (Enrollment $enrollment): array => [
                'course_title' => $enrollment->course->title,
                'course_slug' => $enrollment->course->slug,
                'status' => $enrollment->status,
                'progress_percentage' => $enrollment->progress_percentage,
            ]);

        return Inertia::render('Enrollments/Index', [
            'enrollments' => $enrollments,
        ]);
    }
}
