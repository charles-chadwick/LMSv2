<?php

use App\Http\Controllers\ArchiveCourseController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Course\DiscussionController;
use App\Http\Controllers\Course\RosterController;
use App\Http\Controllers\CourseCatalogController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\Curriculum\CurriculumController;
use App\Http\Controllers\Curriculum\LessonController as CurriculumLessonController;
use App\Http\Controllers\Curriculum\ModuleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\PublishCourseController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('verify-email', EmailVerificationPromptController::class)->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('verified')->name('dashboard');

    Route::middleware('verified')->group(function (): void {
        Route::resource('courses', CourseController::class)->except('show');
        Route::post('courses/{course}/publish', PublishCourseController::class)->name('courses.publish');
        Route::post('courses/{course}/archive', ArchiveCourseController::class)->name('courses.archive');
        Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
        Route::get('courses/{course}/curriculum', [CurriculumController::class, 'show'])->name('curriculum.show');
        Route::get('courses/{course}/students', [RosterController::class, 'index'])->name('courses.roster');
        Route::get('courses/{course}/students/search', [RosterController::class, 'search'])->name('courses.roster.search');
        Route::post('courses/{course}/students', [RosterController::class, 'store'])->name('courses.roster.store');

        Route::post('courses/{course}/modules', [ModuleController::class, 'store'])->name('modules.store');
        Route::put('modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
        Route::delete('modules/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy');
        Route::post('courses/{course}/modules/reorder', [ModuleController::class, 'reorder'])->name('modules.reorder');

        Route::post('modules/{module}/lessons', [CurriculumLessonController::class, 'store'])->name('lessons.store');
        Route::put('lessons/{lesson}', [CurriculumLessonController::class, 'update'])->name('lessons.update');
        Route::delete('lessons/{lesson}', [CurriculumLessonController::class, 'destroy'])->name('lessons.destroy');
        Route::post('modules/{module}/lessons/reorder', [CurriculumLessonController::class, 'reorder'])->name('lessons.reorder');

        Route::get('catalog', [CourseCatalogController::class, 'index'])->name('catalog.index');
        Route::get('catalog/{course}', [CourseCatalogController::class, 'show'])->name('catalog.show');

        Route::get('my-courses', [EnrollmentController::class, 'index'])->name('enrollments.index');

        Route::get('learn/{course}/{lesson}', [LessonController::class, 'show'])->name('lessons.show');

        Route::get('users/{user}', [UserProfileController::class, 'show'])->name('users.show');
        Route::patch('users/{user}', [UserProfileController::class, 'update'])->name('users.update');
        Route::post('users/{user}/avatar', [UserProfileController::class, 'storeAvatar'])->name('users.avatar.store');
        Route::delete('users/{user}/avatar', [UserProfileController::class, 'destroyAvatar'])->name('users.avatar.destroy');

        Route::get('courses/{course}/discussions', [DiscussionController::class, 'index'])->name('discussions.index');
        Route::post('courses/{course}/discussions', [DiscussionController::class, 'store'])->name('discussions.store');
        Route::get('discussions/{discussion}', [DiscussionController::class, 'show'])->name('discussions.show');
    });
});
