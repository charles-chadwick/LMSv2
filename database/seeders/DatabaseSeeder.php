<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Test;
use App\Models\TestAnswer;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // Well-known accounts for signing in during development.
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $primaryInstructor = User::factory()->instructor()->create([
            'name' => 'Instructor User',
            'email' => 'instructor@example.com',
        ]);

        $primaryStudent = User::factory()->student()->create([
            'name' => 'Student User',
            'email' => 'student@example.com',
        ]);

        $instructors = User::factory()->instructor()->count(3)->create()->push($primaryInstructor);
        $students = User::factory()->student()->count(20)->create()->push($primaryStudent);

        $instructors->each(function (User $instructor) use ($students): void {
            Course::factory()
                ->published()
                ->count(2)
                ->for($instructor, 'instructor')
                ->create()
                ->each(fn (Course $course) => $this->buildCourse($course, $students));
        });
    }

    /**
     * Populate a course with content, assessments, discussions and enrollments.
     *
     * @param  Collection<int, User>  $students
     */
    protected function buildCourse(Course $course, Collection $students): void
    {
        // Modules -> lessons.
        $modules = Module::factory()
            ->count(3)
            ->for($course)
            ->sequence(fn ($sequence) => ['position' => $sequence->index])
            ->create();

        $modules->each(function (Module $module): void {
            Lesson::factory()
                ->count(3)
                ->for($module)
                ->sequence(fn ($sequence) => ['position' => $sequence->index])
                ->create();
        });

        $lessons = $course->lessons()->get();

        // Assignments.
        $assignments = Assignment::factory()->count(2)->for($course)->create();

        // A test with graded multiple-choice questions.
        $test = Test::factory()->for($course)->create();

        Question::factory()
            ->count(5)
            ->for($test)
            ->sequence(fn ($sequence) => ['position' => $sequence->index])
            ->create()
            ->each(function (Question $question): void {
                QuestionOption::factory()->correct()->for($question)->create(['position' => 0]);
                QuestionOption::factory()
                    ->count(3)
                    ->for($question)
                    ->sequence(fn ($sequence) => ['position' => $sequence->index + 1])
                    ->create();
            });

        // Discussions with a few replies each.
        Discussion::factory()
            ->count(2)
            ->for($course)
            ->state(fn () => ['user_id' => $students->random()->id])
            ->create()
            ->each(function (Discussion $discussion) use ($students): void {
                DiscussionReply::factory()
                    ->count(fake()->numberBetween(1, 4))
                    ->for($discussion)
                    ->state(fn () => ['user_id' => $students->random()->id])
                    ->create();
            });

        // Enroll a slice of students; mix of completed and in-progress.
        $enrolled = $students->random(8)->values();

        $enrolled->each(function (User $student, int $index) use ($course, $lessons): void {
            $enrollment = Enrollment::factory()
                ->for($student, 'student')
                ->for($course)
                ->when($index < 3, fn ($factory) => $factory->completed())
                ->create();

            if ($enrollment->status === 'completed') {
                // Freeze the course content the student originally learned.
                $enrollment->update([
                    'content_snapshot' => $course->load('modules.lessons')->toArray(),
                ]);

                $enrollment->lessonCompletions()->createMany(
                    $lessons->map(fn (Lesson $lesson) => [
                        'lesson_id' => $lesson->id,
                        'completed_at' => now(),
                    ])->all()
                );

                $enrollment->certificate()->create([
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'final_grade' => $enrollment->final_grade,
                    'issued_at' => now(),
                ]);

                return;
            }

            // Partial progress for active students.
            $done = $lessons->take(fake()->numberBetween(0, max(0, $lessons->count() - 1)));

            $enrollment->lessonCompletions()->createMany(
                $done->map(fn (Lesson $lesson) => [
                    'lesson_id' => $lesson->id,
                    'completed_at' => now(),
                ])->all()
            );

            $enrollment->update([
                'progress_percentage' => $lessons->count() > 0
                    ? (int) round($done->count() / $lessons->count() * 100)
                    : 0,
            ]);
        });

        $this->buildAssessmentActivity($course, $assignments, $test, $enrolled);
    }

    /**
     * Generate assignment submissions and test attempts for enrolled students.
     *
     * @param  Collection<int, Assignment>  $assignments
     * @param  Collection<int, User>  $enrolled
     */
    protected function buildAssessmentActivity(Course $course, Collection $assignments, Test $test, Collection $enrolled): void
    {
        $instructor = $course->instructor;
        $questions = $test->questions()->with('options')->get();

        // Roughly half the class submits each assignment; some are graded.
        $assignments->each(function (Assignment $assignment) use ($enrolled, $instructor): void {
            $enrolled->random((int) ceil($enrolled->count() / 2))->each(function (User $student, int $index) use ($assignment, $instructor): void {
                AssignmentSubmission::factory()
                    ->for($assignment)
                    ->for($student, 'student')
                    ->when($index % 2 === 0, fn ($factory) => $factory->graded()->state([
                        'graded_by' => $instructor->id,
                        'score' => fake()->randomFloat(2, 0, $assignment->points_possible),
                    ]))
                    ->create();
            });
        });

        // Roughly half the class attempts the test; grade each answer against the correct option.
        $enrolled->random((int) ceil($enrolled->count() / 2))->each(function (User $student) use ($test, $questions, $instructor): void {
            $attempt = TestAttempt::factory()
                ->for($test)
                ->for($student, 'student')
                ->graded()
                ->state(['graded_by' => $instructor->id])
                ->create();

            $earned = 0;

            $questions->each(function (Question $question) use ($attempt, &$earned): void {
                $correct = $question->options->firstWhere('is_correct', true);
                $chosen = fake()->boolean(65) ? $correct : $question->options->random();
                $isCorrect = $chosen?->is($correct) ?? false;
                $points = $isCorrect ? $question->points : 0;
                $earned += $points;

                TestAnswer::factory()
                    ->for($attempt, 'attempt')
                    ->for($question)
                    ->create([
                        'question_option_id' => $chosen?->id,
                        'answer_text' => null,
                        'is_correct' => $isCorrect,
                        'points_awarded' => $points,
                    ]);
            });

            $attempt->update(['score' => $earned]);
        });
    }
}
