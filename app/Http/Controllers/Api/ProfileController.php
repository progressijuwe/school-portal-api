<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePhotoRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(protected CloudinaryService $cloudinary) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('department.faculty', 'profile');

        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully.',
            'data'    => new UserResource($user),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($request->has('name')) {
            $user->update(['name' => $request->name]);
        }

        // Update contact profile
       $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $request->only([
                'phone',
                'address',
                'date_of_birth',
                'emergency_contact_name',
                'emergency_contact_phone',
            ])
        );

        // Update lecturer profile if applicable
        if ($user->hasRole('lecturer') && $request->hasAny([
            'prefix',
            'highest_qualification',
            'specialization',
        ])) {
            $user->lecturerProfile()->updateOrCreate(
                ['user_id' => $user->id],
                $request->only([
                    'prefix',
                    'highest_qualification',
                    'specialization',
                ])
            );
        }

        $user->load('department.faculty', 'profile', 'lecturerProfile');

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data'    => new UserResource($user),
        ]);
    }

    public function updatePhoto(UpdatePhotoRequest $request): JsonResponse
    {
        $user = $request->user();

        // Delete old photo from Cloudinary if it exists
        if ($user->profile_photo_public_id) {
            $this->cloudinary->deletePhoto($user->profile_photo_public_id);
        }

        $result = $this->cloudinary->uploadProfilePhoto($request->file('photo'));

        $user->update([
            'profile_photo_url'       => $result['url'],
            'profile_photo_public_id' => $result['public_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile photo updated successfully.',
            'data'    => [
                'profile_photo_url' => $result['url'],
            ],
        ]);
    }

    public function removePhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->profile_photo_public_id) {
            return response()->json([
                'success' => false,
                'message' => 'No profile photo to remove.',
            ], 409);
        }

        $this->cloudinary->deletePhoto($user->profile_photo_public_id);

        $user->update([
            'profile_photo_url'       => null,
            'profile_photo_public_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile photo removed successfully.',
        ]);
    }
}