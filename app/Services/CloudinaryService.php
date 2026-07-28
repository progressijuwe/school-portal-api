<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    protected ?Cloudinary $cloudinary = null;

    protected function client(): Cloudinary
    {
        if ($this->cloudinary) {
            return $this->cloudinary;
        }

        Configuration::instance([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);

        return $this->cloudinary = new Cloudinary();
    }

    public function uploadProfilePhoto(UploadedFile $file): array
    {
        $result = $this->client()
            ->uploadApi()
            ->upload(
                $file->getRealPath(),
                [
                    'folder' => 'school_portal/profile_photos',
                    'transformation' => [
                        [
                            'width' => 400,
                            'height' => 400,
                            'crop' => 'fill',
                            'gravity' => 'face',
                        ],
                        [
                            'quality' => 'auto',
                            'fetch_format' => 'auto',
                        ],
                    ],
                ]
            );

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    public function deletePhoto(string $publicId): void
    {
        $this->client()
            ->uploadApi()
            ->destroy($publicId);
    }
}