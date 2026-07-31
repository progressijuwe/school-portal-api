<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:2048', // 2MB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Please select a photo to upload.',
            'photo.image' => 'The file must be an image.',
            'photo.mimes' => 'Only JPEG, JPG, PNG and WebP images are allowed.',
            'photo.max' => 'Photo must not exceed 2MB.',
        ];
    }
}
