<?php

use App\Actions\DropEnrollment;
use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;

test('it transitions an active enrollment to dropped and preserves progress', function (): void {
    $enrollment = Enrollment::factory()->create([
        'status' => EnrollmentStatus::Active,
        'progress_percentage' => 40,
    ]);

    $result = DropEnrollment::run($enrollment);

    expect($result->status)->toBe(EnrollmentStatus::Dropped)
        ->and($result->progress_percentage)->toBe(40)
        ->and($enrollment->fresh()->status)->toBe(EnrollmentStatus::Dropped);
});

test('it does not overwrite a completed enrollment', function (): void {
    $enrollment = Enrollment::factory()->completed()->create();

    $result = DropEnrollment::run($enrollment);

    expect($result->status)->toBe(EnrollmentStatus::Completed)
        ->and($enrollment->fresh()->status)->toBe(EnrollmentStatus::Completed);
});
