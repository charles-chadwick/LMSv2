<?php

use App\Actions\EnrollStudent;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

test('it creates a new active enrollment', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $enrollment = EnrollStudent::run($student, $course);

    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->enrolled_at)->not->toBeNull();
});

test('it reactivates a dropped enrollment without creating a new row or losing progress', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $original = Enrollment::factory()->for($student, 'student')->for($course)->create([
        'status' => EnrollmentStatus::Dropped,
        'progress_percentage' => 60,
        'enrolled_at' => now()->subMonth(),
    ]);

    $enrollment = EnrollStudent::run($student, $course);

    expect($enrollment->id)->toBe($original->id)
        ->and($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->progress_percentage)->toBe(60)
        ->and($enrollment->enrolled_at->toDateString())->toBe($original->enrolled_at->toDateString())
        ->and(Enrollment::where(['user_id' => $student->id, 'course_id' => $course->id])->count())->toBe(1);
});
