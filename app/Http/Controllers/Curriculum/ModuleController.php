<?php

namespace App\Http\Controllers\Curriculum;

use App\Actions\ReorderModules;
use App\Http\Controllers\Controller;
use App\Http\Requests\Curriculum\StoreModuleRequest;
use App\Http\Requests\Curriculum\UpdateModuleRequest;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function store(StoreModuleRequest $request, Course $course): RedirectResponse
    {
        $this->authorize('manageContent', $course);

        $course->modules()->create([
            ...$request->validated(),
            'position' => (int) $course->modules()->max('position') + 1,
        ]);

        return back()->with('status', 'Module created.');
    }

    public function update(UpdateModuleRequest $request, Module $module): RedirectResponse
    {
        $this->authorize('manageContent', $module->course);

        $module->update($request->validated());

        return back()->with('status', 'Module updated.');
    }

    public function destroy(Module $module): RedirectResponse
    {
        $this->authorize('manageContent', $module->course);

        $module->delete();

        return back()->with('status', 'Module deleted.');
    }

    public function reorder(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('manageContent', $course);

        $validated = $request->validate([
            'modules' => ['required', 'array'],
            'modules.*' => ['integer'],
        ]);

        ReorderModules::run($course, $validated['modules']);

        return back()->with('status', 'Modules reordered.');
    }
}
