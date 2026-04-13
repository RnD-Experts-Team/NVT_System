<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DayScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'date'          => ['required', 'date_format:Y-m-d'],
            'with_history'  => ['nullable', 'boolean'],
        ];
    }
}
