<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportExcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'          => ['required', 'file', 'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain,application/csv,application/octet-stream,application/vnd.ms-excel'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'week_start'    => ['required', 'date_format:Y-m-d'],
        ];
    }
}
