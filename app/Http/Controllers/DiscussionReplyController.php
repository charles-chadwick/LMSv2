<?php

namespace App\Http\Controllers;

use App\Actions\CreateReply;
use App\Http\Requests\Discussion\StoreReplyRequest;
use App\Http\Requests\Discussion\UpdateReplyRequest;
use App\Models\Discussion;
use App\Models\DiscussionReply;
use Illuminate\Http\RedirectResponse;

class DiscussionReplyController extends Controller
{
    public function store(StoreReplyRequest $request, Discussion $discussion): RedirectResponse
    {
        $this->authorize('reply', $discussion);

        CreateReply::run($discussion, $request->user(), $request->validated());

        return redirect()->route('discussions.show', $discussion)->with('status', 'Reply posted.');
    }

    public function update(UpdateReplyRequest $request, DiscussionReply $reply): RedirectResponse
    {
        $this->authorize('update', $reply);

        $reply->update($request->validated());

        return redirect()->route('discussions.show', $reply->discussion_id)->with('status', 'Reply updated.');
    }

    public function destroy(DiscussionReply $reply): RedirectResponse
    {
        $this->authorize('delete', $reply);

        $discussionId = $reply->discussion_id;
        $reply->delete();

        return redirect()->route('discussions.show', $discussionId)->with('status', 'Reply deleted.');
    }
}
