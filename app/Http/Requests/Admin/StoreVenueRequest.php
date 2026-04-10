<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVenueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'code'     => ['required', 'string', 'max:20', 'unique:venues,code'],
            'type'     => ['required', 'in:lecture_hall,laboratory,seminar_room,workshop'],
            'capacity' => ['required', 'integer', 'min:1'],
        ];
    }
}
