<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserSummaryResource extends JsonResource
{
    /**
     * The single source of truth for how a user is shaped for the frontend
     * wherever their name/avatar is displayed.
     *
     * @return array{id: int, name: string, role: string, avatar_thumb: ?string, avatar_preview: ?string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->primaryRoleName(),
            'avatar_thumb' => $this->avatar_thumb_url,
            'avatar_preview' => $this->avatar_preview_url,
        ];
    }

    /**
     * The user's first assigned role, or "Member" when they have none.
     */
    private function primaryRoleName(): string
    {
        return $this->getRoleNames()->first() ?? 'Member';
    }
}
