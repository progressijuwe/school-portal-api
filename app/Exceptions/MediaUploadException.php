<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Raised when an upstream media provider (Cloudinary) is unreachable or
 * rejects an upload.
 *
 * Carries a 503 so the API envelope reports a transient upstream failure
 * rather than an unexplained 500 — the user can meaningfully retry.
 */
class MediaUploadException extends RuntimeException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 503;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return ['Retry-After' => '30'];
    }
}
