<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Only users who may provision accounts can create.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in($this->allowedRoles())],
        ];
    }

    /**
     * Roles the current actor may assign (admins: any; others: student only).
     *
     * @return list<string>
     */
    private function allowedRoles(): array
    {
        if ($this->user()->hasRole(UserRole::Admin->value)) {
            return array_map(fn (UserRole $role): string => $role->value, UserRole::cases());
        }

        return [UserRole::Student->value];
    }
}
