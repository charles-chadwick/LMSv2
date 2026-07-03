<?php

namespace App\Http\Controllers\Curriculum;

use App\Actions\ReorderLessons;
use App\Http\Controllers\Controller;
use App\Http\Requests\Curriculum\StoreLessonRequest;
use App\Http\Requests\Curriculum\UpdateLessonRequest;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LessonController extends Controller
{
    public function store(StoreLessonRequest $request, Module $module): RedirectResponse
    {
        $this->authorize('manageContent', $module->course);

        $validated = $request->validated();

        $module->lessons()->create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['title']),
            'position' => (int) $module->lessons()->max('position') + 1,
        ]);

        return back()->with('status', 'Lesson created.');
    }

    public function update(UpdateLessonRequest $request, Lesson $lesson): RedirectResponse
    {
        $this->authorize('manageContent', $lesson->module->course);

        $lesson->update($request->validated());

        return back()->with('status', 'Lesson updated.');
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        $this->authorize('manageContent', $lesson->module->course);

        $lesson->delete();

        return back()->with('status', 'Lesson deleted.');
    }

    public function reorder(Request $request, Module $module): RedirectResponse
    {
        $this->authorize('manageContent', $module->course);

        $validated = $request->validate([
            'lessons' => ['required', 'array'],
            'lessons.*' => ['integer'],
        ]);

        ReorderLessons::run($module, $validated['lessons']);

        return back()->with('status', 'Lessons reordered.');
    }

    /**
     * Build a globally unique lesson slug (including soft-deleted rows, which
     * keep their slug against the global unique index).
     */
    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (Lesson::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
