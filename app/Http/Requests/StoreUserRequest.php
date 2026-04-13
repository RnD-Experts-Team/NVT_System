<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:255'],
            'nickname'           => ['nullable', 'string', 'max:100'],
            'ac_no'              => ['required', 'string', 'max:50', 'unique:users,ac_no'],
            'email'              => ['required', 'email', 'unique:users,email'],
            'password'           => ['required', 'string', 'min:8'],
            'department_id'      => ['required', 'exists:departments,id'],
            'user_level_id'      => ['required', 'exists:user_levels,id'],
            'user_level_tier_id' => ['nullable', 'exists:user_level_tiers,id'],
            'roles'              => ['nullable', 'array'],
            'roles.*'            => ['string', 'exists:roles,name'],
        ];
    }
}
