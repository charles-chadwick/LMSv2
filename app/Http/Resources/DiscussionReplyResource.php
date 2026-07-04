<?php

namespace App\Http\Resources;

use App\Models\DiscussionReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DiscussionReply
 */
class DiscussionReplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'discussion_id' => $this->discussion_id,
            'parent_id' => $this->parent_id,
            'body' => $this->body,
            'author' => UserSummaryResource::make($this->author)->resolve($request),
            'created_at' => $this->created_at?->toIso8601String(),
            // Resolved eagerly (rather than left as an unresolved ResourceCollection) so
            // Inertia's props resolver doesn't treat it as Responsable and wrap it in a
            // "data" key when it serializes the response.
            'children' => $this->whenLoaded(
                'childrenRecursive',
                fn () => DiscussionReplyResource::collection($this->childrenRecursive)->resolve($request)
            ),
        ];
    }
}
