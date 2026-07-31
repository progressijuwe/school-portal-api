<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MediaUploadException;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class CloudinaryService
{
    private ?Cloudinary $cloudinary = null;

    private function client(): Cloudinary
    {
        if ($this->cloudinary !== null) {
            return $this->cloudinary;
        }

        Configuration::instance([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
            'url' => ['secure' => true],
        ]);

        return $this->cloudinary = new Cloudinary;
    }

    /**
     * @return array{url: string, public_id: string}
     *
     * @throws MediaUploadException
     */
    public function uploadProfilePhoto(UploadedFile $file): array
    {
        try {
            $result = $this->client()->uploadApi()->upload($file->getRealPath(), [
                'folder' => 'school_portal/profile_photos',
                // Bounded so a Cloudinary outage cannot hold a PHP worker open
                // until the web server times the whole request out.
                'timeout' => (int) config('services.cloudinary.timeout', 15),
                'transformation' => [
                    ['width' => 400, 'height' => 400, 'crop' => 'fill', 'gravity' => 'face'],
                    ['quality' => 'auto', 'fetch_format' => 'auto'],
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            throw new MediaUploadException(
                'Photo upload is temporarily unavailable. Please try again shortly.',
                previous: $e,
            );
        }

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    /**
     * Best-effort delete.
     *
     * A failure here leaves an orphaned asset in Cloudinary, which costs a few
     * kilobytes. Propagating it would fail the user's request for no benefit,
     * so it is logged instead — the caller has already replaced the reference.
     */
    public function deletePhoto(string $publicId): void
    {
        try {
            $this->client()->uploadApi()->destroy($publicId);
        } catch (Throwable $e) {
            Log::warning('Failed to delete Cloudinary asset.', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
