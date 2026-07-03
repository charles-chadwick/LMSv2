<?php

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('the enrollStudents ability allows an instructor on their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);

    expect($instructor->can('enrollStudents', $course))->toBeTrue();
});

test('the enrollStudents ability denies an instructor on another instructors course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create();

    expect($instructor->can('enrollStudents', $course))->toBeFalse();
});

test('the enrollStudents ability allows an admin on any course', function (): void {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->published()->create();

    expect($admin->can('enrollStudents', $course))->toBeTrue();
});

test('the enrollStudents ability denies a student', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    expect($student->can('enrollStudents', $course))->toBeFalse();
});

test('an instructor can enroll a student into their own course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);

    $this->actingAs($instructor)
        ->post(route('courses.roster.store', $course), ['student_id' => $student->id])
        ->assertRedirect();

    $enrollment = Enrollment::where(['user_id' => $student->id, 'course_id' => $course->id])->sole();
    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->enrolled_at)->not->toBeNull();
});

test('an admin can enroll a student into any course', function (): void {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($admin)
        ->post(route('courses.roster.store', $course), ['student_id' => $student->id])
        ->assertRedirect();

    expect(Enrollment::where(['user_id' => $student->id, 'course_id' => $course->id])->exists())->toBeTrue();
});

test('an instructor cannot enroll a student into another instructors course', function (): void {
    $instructor = User::factory()->instructor()->create();
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($instructor)
        ->post(route('courses.roster.store', $course), ['student_id' => $student->id])
        ->assertForbidden();

    expect(Enrollment::where('course_id', $course->id)->exists())->toBeFalse();
});

test('a student cannot enroll anyone, including themselves', function (): void {
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create();

    $this->actingAs($student)
        ->post(route('courses.roster.store', $course), ['student_id' => $student->id])
        ->assertForbidden();

    expect(Enrollment::where('course_id', $course->id)->exists())->toBeFalse();
});

test('only users with the student role can be enrolled', function (): void {
    $instructor = User::factory()->instructor()->create();
    $notAStudent = User::factory()->instructor()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);

    $this->actingAs($instructor)
        ->post(route('courses.roster.store', $course), ['student_id' => $notAStudent->id])
        ->assertSessionHasErrors('student_id');

    expect(Enrollment::where('course_id', $course->id)->exists())->toBeFalse();
});

test('enrolling a student twice does not create a duplicate enrollment', function (): void {
    $instructor = User::factory()->instructor()->create();
    $student = User::factory()->student()->create();
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);

    $this->actingAs($instructor)->post(route('courses.roster.store', $course), ['student_id' => $student->id]);
    $this->actingAs($instructor)->post(route('courses.roster.store', $course), ['student_id' => $student->id]);

    expect(Enrollment::where(['user_id' => $student->id, 'course_id' => $course->id])->count())->toBe(1);
});

test('a guest cannot enroll a student', function (): void {
    $course = Course::factory()->published()->create();
    $student = User::factory()->student()->create();

    $this->post(route('courses.roster.store', $course), ['student_id' => $student->id])
        ->assertRedirect(route('login'));
});

test('my courses lists only the current users enrollments', function (): void {
    $user = User::factory()->student()->create();
    $other = User::factory()->student()->create();
    $mine = Course::factory()->published()->create(['title' => 'My Course']);
    $theirs = Course::factory()->published()->create(['title' => 'Their Course']);

    $user->enrollments()->create(['course_id' => $mine->id, 'status' => EnrollmentStatus::Active, 'enrolled_at' => now()]);
    $other->enrollments()->create(['course_id' => $theirs->id, 'status' => EnrollmentStatus::Active, 'enrolled_at' => now()]);

    $this->actingAs($user)
        ->get(route('enrollments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Enrollments/Index')
            ->has('enrollments.data', 1)
            ->where('enrollments.data.0.course_title', 'My Course')
            ->where('enrollments.total', 1)
        );
});

test('my courses paginates enrollments', function (): void {
    $user = User::factory()->student()->create();
    $courses = Course::factory()->count(18)->published()->create();

    foreach ($courses as $course) {
        $user->enrollments()->create([
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active,
            'enrolled_at' => now(),
        ]);
    }

    $this->actingAs($user)
        ->get(route('enrollments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('enrollments.data', 15)
            ->where('enrollments.total', 18)
            ->where('enrollments.last_page', 2)
        );
});

test('a guest is redirected to login from my courses', function (): void {
    $this->get(route('enrollments.index'))->assertRedirect(route('login'));
});
