<?php

namespace App\Http\Requests\Lecturer;

use Illuminate\Foundation\Http\FormRequest;

class BatchSubmitGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grades'                 => ['required', 'array', 'min:1'],
            'grades.*.enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'grades.*.ca_score'      => ['required', 'numeric', 'min:0', 'max:20'],
            'grades.*.project_score' => ['required', 'numeric', 'min:0', 'max:20'],
            'grades.*.exam_score'    => ['required', 'numeric', 'min:0', 'max:60'],
        ];
    }
}