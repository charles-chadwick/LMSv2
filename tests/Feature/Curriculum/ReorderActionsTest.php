<?php

use App\Actions\ReorderLessons;
use App\Actions\ReorderModules;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Validation\ValidationException;

test('reorder modules rewrites positions to the given order', function (): void {
    $course = Course::factory()->create();
    $a = Module::factory()->for($course)->create(['position' => 0]);
    $b = Module::factory()->for($course)->create(['position' => 1]);
    $c = Module::factory()->for($course)->create(['position' => 2]);

    ReorderModules::run($course, [$c->id, $a->id, $b->id]);

    expect($c->fresh()->position)->toBe(0)
        ->and($a->fresh()->position)->toBe(1)
        ->and($b->fresh()->position)->toBe(2);
});

test('reorder modules rejects an id from another course', function (): void {
    $course = Course::factory()->create();
    $a = Module::factory()->for($course)->create(['position' => 0]);
    $foreign = Module::factory()->create();

    expect(fn () => ReorderModules::run($course, [$a->id, $foreign->id]))
        ->toThrow(ValidationException::class);

    expect($a->fresh()->position)->toBe(0);
});

test('reorder lessons rewrites positions to the given order', function (): void {
    $module = Module::factory()->create();
    $a = Lesson::factory()->for($module)->create(['position' => 0]);
    $b = Lesson::factory()->for($module)->create(['position' => 1]);

    ReorderLessons::run($module, [$b->id, $a->id]);

    expect($b->fresh()->position)->toBe(0)
        ->and($a->fresh()->position)->toBe(1);
});

test('reorder lessons rejects an id from another module', function (): void {
    $module = Module::factory()->create();
    $a = Lesson::factory()->for($module)->create(['position' => 0]);
    $foreign = Lesson::factory()->create();

    expect(fn () => ReorderLessons::run($module, [$a->id, $foreign->id]))
        ->toThrow(ValidationException::class);
});
