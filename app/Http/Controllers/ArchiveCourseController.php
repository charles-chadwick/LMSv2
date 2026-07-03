<?php

namespace App\Http\Controllers;

use App\Actions\ArchiveCourse;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;

class ArchiveCourseController extends Controller
{
    public function __invoke(Course $course): RedirectResponse
    {
        $this->authorize('archive', $course);

        ArchiveCourse::run($course);

        return back()->with('status', 'Course archived.');
    }
}
