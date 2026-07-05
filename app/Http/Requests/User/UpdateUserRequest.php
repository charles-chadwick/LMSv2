<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Only a manager of the target account may update it.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('user'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ];

        if ($this->user()->hasRole(UserRole::Admin->value)) {
            $rules['role'] = ['required', Rule::in(array_map(
                fn (UserRole $role): string => $role->value,
                UserRole::cases(),
            ))];
        }

        return $rules;
    }
}
