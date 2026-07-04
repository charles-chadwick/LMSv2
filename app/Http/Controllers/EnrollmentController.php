<?php

namespace App\Http\Controllers;

use App\Actions\DropEnrollment;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EnrollmentController extends Controller
{
    /**
     * Enrollments shown per page on the My Courses list.
     */
    private const PER_PAGE = 15;

    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        $this->authorize('drop', $enrollment);

        DropEnrollment::run($enrollment);

        return back()->with('status', 'Enrollment dropped.');
    }

    public function index(Request $request): Response
    {
        $enrollments = $request->user()->enrollments()
            ->with('course:id,title,slug')
            ->latest('enrolled_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Enrollment $enrollment): array => [
                'id' => $enrollment->id,
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
