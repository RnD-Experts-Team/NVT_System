<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id'                   => ['required', 'integer', 'exists:departments,id'],
            'week_start'                      => ['required', 'date_format:Y-m-d'],
            'publish'                         => ['nullable', 'boolean'],
            'assignments'                     => ['required', 'array'],
            'assignments.*.user_id'           => ['required', 'integer', 'exists:users,id'],
            'assignments.*.date'              => ['required', 'date_format:Y-m-d'],
            'assignments.*.type'              => ['required', 'in:shift,day_off,sick_day,leave_request'],
            'assignments.*.shift_id'          => ['nullable', 'integer', 'exists:shifts,id'],
            'assignments.*.is_cover'          => ['nullable', 'boolean'],
            'assignments.*.cover_for_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assignments.*.comment'           => ['nullable', 'string', 'max:1000'],
        ];
    }
}
