<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserManagementResource extends JsonResource
{
    /**
     * User shape for the management list and edit form (includes email/status).
     *
     * @return array{id: int, name: string, first_name: string, last_name: string, email: string, role: string, status: string, avatar_thumb: ?string, created_at: ?string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'role' => $this->getRoleNames()->first() ?? 'Member',
            'status' => $this->email_verified_at ? 'Active' : 'Invited',
            'avatar_thumb' => $this->avatar_thumb_url,
            'created_at' => $this->created_at?->toDateString(),
        ];
    }
}
