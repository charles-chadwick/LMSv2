<?php

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Database\Seeders\ComputerScienceTitles;
use Database\Seeders\FilterData;
use Illuminate\Support\Str;

it('gives a course a CS title, matching slug and censored body', function (): void {
    ComputerScienceTitles::reset(); // Ensure the first pick comes from the pool, not the numbered fallback.

    $course = Course::factory()->readableContent()->create();

    expect(ComputerScienceTitles::COURSE_TITLES)->toContain($course->title)
        ->and($course->slug)->toStartWith(Str::slug($course->title))
        ->and(FilterData::hasBadWords($course->description))->toBeFalse()
        ->and(FilterData::hasBadWords($course->summary))->toBeFalse();
});

it('gives a module a CS topic title and censored description', function (): void {
    $module = Module::factory()->readableContent()->create();

    expect(ComputerScienceTitles::MODULE_TOPICS)->toContain($module->title)
        ->and(FilterData::hasBadWords($module->description))->toBeFalse();
});

it('gives a lesson a CS topic title, matching slug and censored content', function (): void {
    $lesson = Lesson::factory()->readableContent()->create();

    expect(ComputerScienceTitles::LESSON_TOPICS)->toContain($lesson->title)
        ->and($lesson->slug)->toStartWith(Str::slug($lesson->title))
        ->and(FilterData::hasBadWords($lesson->content))->toBeFalse();
});
