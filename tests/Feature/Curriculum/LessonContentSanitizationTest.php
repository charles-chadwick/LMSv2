<?php

use App\Models\Lesson;

test('lesson content is sanitized on save', function (): void {
    $lesson = Lesson::factory()->create([
        'content' => '<p>Safe copy</p><script>alert(1)</script>'
            .'<a href="javascript:alert(2)">x</a><iframe src="evil"></iframe>',
    ]);

    $stored = $lesson->fresh()->content;

    expect($stored)->toContain('Safe copy')
        ->not->toContain('<script>')
        ->not->toContain('javascript:')
        ->not->toContain('<iframe');
});

test('allowed formatting survives sanitization', function (): void {
    $lesson = Lesson::factory()->create([
        'content' => '<p><strong>Bold</strong> and <em>italic</em></p><ul><li>Item</li></ul>',
    ]);

    $stored = $lesson->fresh()->content;

    expect($stored)->toContain('<strong>Bold</strong>')
        ->toContain('<em>italic</em>')
        ->toContain('<li>Item</li>');
});

test('null content stays null', function (): void {
    $lesson = Lesson::factory()->create(['content' => null]);

    expect($lesson->fresh()->content)->toBeNull();
});
