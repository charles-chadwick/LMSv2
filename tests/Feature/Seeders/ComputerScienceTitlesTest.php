<?php

use Database\Seeders\ComputerScienceTitles;

it('exposes non-empty curated title pools', function (): void {
    expect(ComputerScienceTitles::COURSE_TITLES)->not->toBeEmpty()
        ->and(ComputerScienceTitles::MODULE_TOPICS)->not->toBeEmpty()
        ->and(ComputerScienceTitles::LESSON_TOPICS)->not->toBeEmpty();
});

it('returns distinct course titles until the pool is exhausted', function (): void {
    ComputerScienceTitles::reset();

    $count = count(ComputerScienceTitles::COURSE_TITLES);
    $titles = collect(range(1, $count))->map(fn (): string => ComputerScienceTitles::nextCourse());

    expect($titles->unique())->toHaveCount($count);
});

it('still returns a value after the course pool is exhausted', function (): void {
    ComputerScienceTitles::reset();

    $count = count(ComputerScienceTitles::COURSE_TITLES);
    collect(range(1, $count))->each(fn () => ComputerScienceTitles::nextCourse());

    expect(ComputerScienceTitles::nextCourse())->toBeString()->not->toBeEmpty();
});

it('returns module and lesson topics from their pools', function (): void {
    expect(ComputerScienceTitles::MODULE_TOPICS)->toContain(ComputerScienceTitles::nextModule())
        ->and(ComputerScienceTitles::LESSON_TOPICS)->toContain(ComputerScienceTitles::nextLesson());
});
