<?php

namespace App\Http\Resources;

use App\Models\Discussion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Discussion
 */
class DiscussionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'lesson_id' => $this->lesson_id,
            'title' => $this->title,
            'body' => $this->body,
            'is_pinned' => $this->is_pinned,
            'is_locked' => $this->is_locked,
            'author' => UserSummaryResource::make($this->author)->resolve($request),
            'created_at' => $this->created_at?->toIso8601String(),
            'replies_count' => $this->whenCounted('replies'),
            'replies' => DiscussionReplyResource::collection($this->whenLoaded('replies')),
        ];
    }
}
