<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);

        $this->cloudinary = new Cloudinary();
    }

    public function uploadProfilePhoto(UploadedFile $file): array
    {
        $result = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            [
                'folder'         => 'school_portal/profile_photos',
                'transformation' => [
                    [
                        'width'   => 400,
                        'height'  => 400,
                        'crop'    => 'fill',
                        'gravity' => 'face',
                    ],
                    [
                        'quality'      => 'auto',
                        'fetch_format' => 'auto',
                    ],
                ],
            ]
        );

        return [
            'url'       => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    public function deletePhoto(string $publicId): void
    {
        $this->cloudinary->uploadApi()->destroy($publicId);
    }
}