<?php

use App\Actions\ArchiveCourse;
use App\Actions\PublishCourse;
use App\Enums\CourseStatus;
use App\Models\Course;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('publishing a draft course sets status and stamps published_at', function (): void {
    $course = Course::factory()->create([
        'status' => CourseStatus::Draft,
        'published_at' => null,
    ]);

    $result = PublishCourse::run($course);

    expect($result->status)->toBe(CourseStatus::Published)
        ->and($result->published_at)->not->toBeNull();
});

test('publishing does not overwrite an existing published_at', function (): void {
    $original = now()->subWeek();
    $course = Course::factory()->create([
        'status' => CourseStatus::Draft,
        'published_at' => $original,
    ]);

    PublishCourse::run($course);

    expect($course->fresh()->published_at->timestamp)->toBe($original->timestamp);
});

test('archiving a course sets status to archived', function (): void {
    $course = Course::factory()->published()->create();

    $result = ArchiveCourse::run($course);

    expect($result->status)->toBe(CourseStatus::Archived);
});
