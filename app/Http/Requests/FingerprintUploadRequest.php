<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FingerprintUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'       => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'week_start' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
