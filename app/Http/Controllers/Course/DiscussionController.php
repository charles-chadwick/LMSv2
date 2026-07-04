<?php

namespace App\Http\Controllers\Course;

use App\Actions\CreateDiscussion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Discussion\StoreDiscussionRequest;
use App\Http\Requests\Discussion\UpdateDiscussionRequest;
use App\Http\Resources\DiscussionResource;
use App\Models\Course;
use App\Models\Discussion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DiscussionController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Course $course): Response
    {
        $this->authorize('viewAny', [Discussion::class, $course]);

        $discussions = $course->discussions()
            ->forCourseLevel()
            ->with('author')
            ->withCount('replies')
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate(self::PER_PAGE)
            ->through(fn (Discussion $discussion) => DiscussionResource::make($discussion)->resolve());

        return Inertia::render('Discussions/Index', [
            'course' => $course->only('id', 'title', 'slug'),
            'discussions' => $discussions,
        ]);
    }

    public function store(StoreDiscussionRequest $request, Course $course): RedirectResponse
    {
        $this->authorize('create', [Discussion::class, $course]);

        $discussion = CreateDiscussion::run($course, $request->user(), $request->validated());

        return redirect()->route('discussions.show', $discussion)->with('status', 'Question posted.');
    }

    public function show(Discussion $discussion): Response
    {
        $this->authorize('view', $discussion);

        $discussion->load([
            'author',
            'course:id,title,slug',
            'replies' => fn ($query) => $query->whereNull('parent_id')->with('author'),
            'replies.children' => fn ($query) => $query->with('author'),
        ]);

        return Inertia::render('Discussions/Show', [
            'discussion' => DiscussionResource::make($discussion)->resolve(),
        ]);
    }

    public function update(UpdateDiscussionRequest $request, Discussion $discussion): RedirectResponse
    {
        $this->authorize('update', $discussion);

        $discussion->update($request->validated());

        return redirect()->route('discussions.show', $discussion)->with('status', 'Discussion updated.');
    }

    public function destroy(Discussion $discussion): RedirectResponse
    {
        $this->authorize('delete', $discussion);

        $discussion->delete();

        return redirect()->route('discussions.index', $discussion->course)->with('status', 'Discussion deleted.');
    }

    public function pin(Discussion $discussion): RedirectResponse
    {
        $this->authorize('pin', $discussion);

        $discussion->update(['is_pinned' => ! $discussion->is_pinned]);

        return back();
    }

    public function lock(Discussion $discussion): RedirectResponse
    {
        $this->authorize('lock', $discussion);

        $discussion->update(['is_locked' => ! $discussion->is_locked]);

        return back();
    }
}
