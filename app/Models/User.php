<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use CausesActivity, HasFactory, HasRoles, InteractsWithMedia, LogsActivity, Notifiable;

    /**
     * Register the media collections for the model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * URL to the user's avatar, or null when none has been uploaded.
     */
    public function avatarUrl(): ?string
    {
        return $this->getFirstMediaUrl('avatar') ?: null;
    }

    /**
     * Store an image file as this user's avatar, replacing any existing one.
     */
    public function attachAvatar(string $path): ?Media
    {
        if (! is_file($path)) {
            return null;
        }

        return $this->addMedia($path)
            ->preservingOriginal()
            ->toMediaCollection('avatar');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Configure how activity is recorded for the model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Courses this user teaches as the instructor.
     */
    public function coursesTeaching(): HasMany
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    /**
     * Enrollment records for this user as a student.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Courses this user is enrolled in as a student.
     */
    public function enrolledCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'enrollments')
            ->withPivot(['status', 'progress_percentage', 'completed_at'])
            ->withTimestamps();
    }

    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function testAttempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
