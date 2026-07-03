<?php

namespace App\Http\Controllers;

use App\Actions\PublishCourse;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;

class PublishCourseController extends Controller
{
    public function __invoke(Course $course): RedirectResponse
    {
        $this->authorize('publish', $course);

        PublishCourse::run($course);

        return back()->with('status', 'Course published.');
    }
}
