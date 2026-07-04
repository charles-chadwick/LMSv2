<?php

use App\Models\Course;
use App\Models\Discussion;
use App\Models\Lesson;

it('scopes course-level discussions (no lesson) separately from lesson-level ones', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create();

    $courseLevel = Discussion::factory()->create(['course_id' => $course->id, 'lesson_id' => null]);
    $lessonLevel = Discussion::factory()->create(['course_id' => $course->id, 'lesson_id' => $lesson->id]);

    expect(Discussion::forCourseLevel()->pluck('id'))->toContain($courseLevel->id)->not->toContain($lessonLevel->id)
        ->and(Discussion::forLesson($lesson->id)->pluck('id'))->toContain($lessonLevel->id)->not->toContain($courseLevel->id);
});

it('belongs to a lesson when lesson_id is set', function () {
    $lesson = Lesson::factory()->create();
    $discussion = Discussion::factory()->create(['lesson_id' => $lesson->id]);

    expect($discussion->lesson->id)->toBe($lesson->id);
});
