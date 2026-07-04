<?php

namespace App\Http\Requests\Discussion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $discussion = $this->route('discussion');

        return [
            'body' => ['required', 'string'],
            'parent_id' => [
                'nullable',
                Rule::exists('discussion_replies', 'id')->where('discussion_id', $discussion->id),
            ],
        ];
    }
}
