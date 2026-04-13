<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shiftId = $this->route('shift')?->id;

        return [
            'name'       => ['sometimes', 'required', 'string', 'max:100', Rule::unique('shifts', 'name')->ignore($shiftId)],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time'   => ['sometimes', 'required', 'date_format:H:i'],
            'is_active'  => ['boolean'],
        ];
    }
}
