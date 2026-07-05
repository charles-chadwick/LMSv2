<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use App\Models\Concerns\Filters\CallbackFilter;
use App\Models\Concerns\Filters\Filter;
use App\Models\Concerns\Filters\RangeFilter;
use App\Models\Concerns\Filters\RelationFilter;
use App\Models\Concerns\Searchable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['first_name', 'last_name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use CausesActivity, Filterable, HasFactory, HasRoles, InteractsWithMedia, LogsActivity, Notifiable, Searchable, SoftDeletes;

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
     * Register the single-file avatar collection with display conversions.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatars')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Generate the inline (thumb) and popover/profile (preview) avatar sizes.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 64, 64)
            ->nonQueued()
            ->performOnCollections('avatars');

        $this->addMediaConversion('preview')
            ->fit(Fit::Crop, 160, 160)
            ->nonQueued()
            ->performOnCollections('avatars');
    }

    /**
     * URL of the small inline avatar, or null when none uploaded.
     */
    protected function avatarThumbUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatarUrlForConversion('thumb'));
    }

    /**
     * URL of the larger popover/profile avatar, or null when none uploaded.
     */
    protected function avatarPreviewUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatarUrlForConversion('preview'));
    }

    /**
     * Resolve a conversion URL for the avatar, or null when no avatar exists.
     */
    private function avatarUrlForConversion(string $conversion): ?string
    {
        $url = $this->getFirstMediaUrl('avatars', $conversion);

        return $url === '' ? null : $url;
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
     * Fields searched with LIKE on the management user list.
     *
     * @return list<string>
     */
    protected function searchableFields(): array
    {
        return ['first_name', 'last_name', 'email'];
    }

    /**
     * Filterable fields for the management user list.
     *
     * @return array<string, Filter>
     */
    protected function filterableFields(): array
    {
        return [
            'role' => new RelationFilter('roles', 'name'),
            'status' => new CallbackFilter(function (Builder $query, mixed $value): void {
                $values = (array) $value;

                $query->where(function (Builder $query) use ($values): void {
                    if (in_array('Active', $values, true)) {
                        $query->orWhereNotNull('email_verified_at');
                    }

                    if (in_array('Invited', $values, true)) {
                        $query->orWhereNull('email_verified_at');
                    }
                });
            }),
            'created_at' => new RangeFilter('created_at', asDate: true),
        ];
    }

    /**
     * The user who provisioned this account (null for seeded accounts).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Accounts this user has provisioned.
     */
    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
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
     * Use the app's filterable Notification model for all notification reads.
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
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
