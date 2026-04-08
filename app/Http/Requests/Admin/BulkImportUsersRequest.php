<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkImportUsersRequest extends FormRequest
{
    public function authorize(): bool 
    { 
        return true; 
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'role' => ['required', 'in:student,lecturer'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'The import file must be a CSV.',
            'file.max'   => 'The CSV file must not exceed 2MB.',
        ];
    }
}