<?php

namespace App\Http\Controllers;

use App\Actions\EnrollStudent;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('enroll', $course);

        EnrollStudent::run($request->user(), $course);

        return back()->with('status', 'Enrolled.');
    }
}
