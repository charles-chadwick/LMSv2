<?php

namespace App\Http\Controllers\Course;

use App\Actions\EnrollStudent;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Course\EnrollStudentRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RosterController extends Controller
{
    public function index(Course $course): Response
    {
        $this->authorize('viewRoster', $course);

        $students = $course->enrollments()
            ->select(['id', 'user_id', 'status', 'progress_percentage', 'enrolled_at'])
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

    /**
     * Search the enrollable student pool by name for the add-student field.
     *
     * Returns students (by role) not already active or completed in this
     * course, capped so the full roster is never shipped to the client.
     */
    public function search(Request $request, Course $course): JsonResponse
    {
        $this->authorize('enrollStudents', $course);

        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return response()->json([]);
        }

        $unavailable_ids = $course->enrollments()
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->pluck('user_id');

        $students = User::role(UserRole::Student->value)
            ->whereNotIn('id', $unavailable_ids)
            ->where('name', 'like', '%'.$term.'%')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name'])
            ->map(fn (User $student): array => [
                'id' => $student->id,
                'name' => $student->name,
            ]);

        return response()->json($students);
    }

    public function store(EnrollStudentRequest $request, Course $course): RedirectResponse
    {
        $this->authorize('enrollStudents', $course);

        $student = User::role(UserRole::Student->value)->find($request->integer('student_id'));

        if ($student === null) {
            throw ValidationException::withMessages([
                'student_id' => 'Select a valid student to enroll.',
            ]);
        }

        EnrollStudent::run($student, $course);

        return back()->with('status', 'Student enrolled.');
    }
}
