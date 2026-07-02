<?php

namespace App\Models;

use App\Enums\TestAttemptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestAttempt extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'test_id',
        'user_id',
        'attempt_number',
        'status',
        'score',
        'graded_by',
        'started_at',
        'submitted_at',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TestAttemptStatus::class,
            'score' => 'decimal:2',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TestAnswer::class);
    }
}
