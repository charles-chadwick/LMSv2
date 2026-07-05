<?php

namespace Database\Seeders;

use App\Actions\CompleteCourse;
use App\Actions\CompleteLesson;
use App\Actions\EnrollStudent;
use App\Actions\GradeAssignmentSubmission;
use App\Actions\ScoreTestAttempt;
use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Test;
use App\Models\TestAnswer;
use App\Models\TestAttempt;
use App\Models\User;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(UserTableSeeder::class);

        // Users are seeded from the Rick and Morty character data; pull the
        // instructors and students back out to build course content around them.
        $instructors = User::role(UserRole::Instructor->value)->get();
        $students = User::role(UserRole::Student->value)->get();

        $instructors->each(function (User $instructor) use ($students): void {
            Course::factory()
                ->published()
                ->readableContent()
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
        // Modules -> lessons (keep the created lessons in memory rather than re-querying).
        $modules = Module::factory()
            ->count(3)
            ->for($course)
            ->readableContent()
            ->sequence($this->positionSequence())
            ->create();

        $lessons = $modules->flatMap(fn (Module $module) => Lesson::factory()
            ->count(3)
            ->for($module)
            ->readableContent()
            ->sequence($this->positionSequence())
            ->create());

        // Assignments.
        $assignments = Assignment::factory()->count(2)->for($course)->create();

        // A test with graded multiple-choice questions; keep questions and options in memory.
        $test = Test::factory()->for($course)->create();

        $questions = Question::factory()
            ->count(5)
            ->for($test)
            ->sequence($this->positionSequence())
            ->create()
            ->each(function (Question $question): void {
                $correct = QuestionOption::factory()->correct()->for($question)->create(['position' => 0]);
                $others = QuestionOption::factory()
                    ->count(3)
                    ->for($question)
                    ->sequence(fn ($sequence) => ['position' => $sequence->index + 1])
                    ->create();

                $question->setRelation('options', $others->prepend($correct));
            });

        $test->setRelation('questions', $questions);

        // Discussions with a few replies each, voiced with (bad-word filtered)
        // lines lifted from the Rick and Morty scripts.
        Discussion::factory()
            ->count(2)
            ->for($course)
            ->state(fn () => [
                'user_id' => $students->random()->id,
                'title' => RickAndMortyDialogue::next(),
                'body' => self::dialogueLines(fake()->numberBetween(2, 4)),
            ])
            ->create()
            ->each(function (Discussion $discussion) use ($students): void {
                DiscussionReply::factory()
                    ->count(fake()->numberBetween(1, 4))
                    ->for($discussion)
                    ->state(fn () => [
                        'user_id' => $students->random()->id,
                        'body' => self::dialogueLines(fake()->numberBetween(1, 3)),
                    ])
                    ->create();
            });

        // Enroll a slice of students; mix of completed and in-progress, driven by the domain Actions.
        $enrolled = $students->random(8)->values();

        $enrolled->each(function (User $student, int $index) use ($course, $lessons): void {
            $enrollment = EnrollStudent::run($student, $course);

            if ($index < 3) {
                // Finish every lesson, then complete the course (freezes snapshot + issues certificate).
                $lessons->each(fn (Lesson $lesson) => CompleteLesson::run($enrollment, $lesson));
                CompleteCourse::run($enrollment, fake()->randomFloat(2, 60, 100));

                return;
            }

            // Partial progress for active students.
            $lessons->take(fake()->numberBetween(0, max(0, $lessons->count() - 1)))
                ->each(fn (Lesson $lesson) => CompleteLesson::run($enrollment, $lesson));
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
        $questions = $test->questions; // Built in-memory by buildCourse, options already attached.

        // Roughly half the class submits each assignment; grade every other submission.
        $assignments->each(function (Assignment $assignment) use ($enrolled, $instructor): void {
            $enrolled->random((int) ceil($enrolled->count() / 2))->each(function (User $student, int $index) use ($assignment, $instructor): void {
                $submission = AssignmentSubmission::factory()
                    ->for($assignment)
                    ->for($student, 'student')
                    ->create();

                if ($index % 2 === 0) {
                    GradeAssignmentSubmission::run(
                        $submission,
                        fake()->randomFloat(2, 0, $assignment->points_possible),
                        fake()->sentence(),
                        $instructor,
                    );
                }
            });
        });

        // Roughly half the class attempts the test; the Action auto-grades each attempt.
        $enrolled->random((int) ceil($enrolled->count() / 2))->each(function (User $student) use ($test, $questions, $instructor): void {
            $attempt = TestAttempt::factory()
                ->for($test)
                ->for($student, 'student')
                ->submitted()
                ->create();

            $questions->each(function (Question $question) use ($attempt): void {
                $correct = $question->options->firstWhere('is_correct', true);
                $chosen = fake()->boolean(65) ? $correct : $question->options->random();

                TestAnswer::factory()
                    ->for($attempt, 'attempt')
                    ->for($question)
                    ->create([
                        'question_option_id' => $chosen?->id,
                        'answer_text' => null,
                    ]);
            });

            ScoreTestAttempt::run($attempt, $instructor);
        });
    }

    /**
     * A factory sequence that numbers rows by their creation order.
     */
    protected function positionSequence(): Closure
    {
        return fn ($sequence) => ['position' => $sequence->index];
    }

    /**
     * Join the given number of clean Rick and Morty dialogue lines into a
     * paragraph-style block of body text.
     */
    protected static function dialogueLines(int $count): string
    {
        return collect(range(1, $count))
            ->map(fn (): string => RickAndMortyDialogue::next())
            ->implode("\n\n");
    }
}
