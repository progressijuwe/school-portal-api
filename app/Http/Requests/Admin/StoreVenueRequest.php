<?php

namespace App\Http\Requests\Admin;

use App\Enums\VenueType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'name' => ['required', 'string', 'max:255'],
            // The column and the API resource have always carried `building`,
            // but neither request accepted it — so the field the student
            // timetable prints above the room number could never be set.
            'building' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:venues,code'],
            'type' => ['required', Rule::enum(VenueType::class)],
            'capacity' => ['required', 'integer', 'min:1'],
            // Accepted so the created venue reflects what was submitted. It was
            // missing, so validated() dropped it and the response reported
            // is_active: null while the column took its database default —
            // a venue that read as inactive until the list was refetched.
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
