<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('keeps the progress meter fresh when navigating back through lessons', function (): void {
    $user = User::factory()->student()->create();
    $course = Course::factory()->published()->create();
    $module = Module::factory()->for($course)->create(['position' => 0]);
    $lesson_a = Lesson::factory()->for($module)->create(['position' => 0]);
    $lesson_b = Lesson::factory()->for($module)->create(['position' => 1]);
    $user->enrollments()->create([
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active,
        'enrolled_at' => now(),
    ]);
    $this->actingAs($user);

    $page = visit(route('lessons.show', [$course, $lesson_a]));

    // Viewing lesson A marks it complete: 1 of 2 lessons.
    $page->assertSee('50%')
        ->click($lesson_b->title)
        ->waitForText('100%')
        // Browser Back restores lesson A from history, whose cached progress
        // predates completing lesson B. The meter must refresh to the current value.
        ->back()
        ->waitForText('100%')
        ->assertDontSee('50%')
        ->assertNoJavaScriptErrors();
});
