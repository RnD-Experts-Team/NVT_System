<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CopyLastWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'week_start'    => ['required', 'date_format:Y-m-d'],
        ];
    }
}
