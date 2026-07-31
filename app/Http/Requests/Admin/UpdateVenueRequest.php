<?php

namespace App\Http\Requests\Admin;

use App\Enums\VenueType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVenueRequest extends FormRequest
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
        $venueId = $this->route('venue')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('venues', 'code')->ignore($venueId)],
            'type' => ['sometimes', Rule::enum(VenueType::class)],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
