<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mews\Purifier\Facades\Purifier;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Lesson extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'module_id',
        'title',
        'slug',
        'content',
        'position',
        'duration_minutes',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Sanitize lesson HTML on write so only safe formatting is ever stored.
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null ? null : Purifier::clean($value, 'lesson'),
        );
    }
}
