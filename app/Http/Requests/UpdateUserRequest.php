<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name'               => ['sometimes', 'required', 'string', 'max:255'],
            'nickname'           => ['nullable', 'string', 'max:100'],
            'ac_no'              => ['nullable', 'string', 'max:50', 'unique:users,ac_no,' . $userId],
            'email'              => ['sometimes', 'required', 'email', 'unique:users,email,' . $userId],
            'password'           => ['sometimes', 'required', 'string', 'min:8'],
            'department_id'      => ['sometimes', 'required', 'exists:departments,id'],
            'user_level_id'      => ['sometimes', 'required', 'exists:user_levels,id'],
            'user_level_tier_id' => ['nullable', 'exists:user_level_tiers,id'],
        ];
    }
}
