<?php

namespace App\Support;

use App\Models\School;
use Illuminate\Support\Facades\Storage;

class ReportBranding
{
    /**
     * Resolve a logo as a DomPDF-safe data URI.
     *
     * Order: school upload → default logo for school code → PGOS mark.
     */
    public static function logoDataUri(?School $school = null): ?string
    {
        if ($school?->logo_path) {
            $uri = self::dataUriFromDisk('public', $school->logo_path);
            if ($uri !== null) {
                return $uri;
            }
        }

        $defaultRelative = self::defaultLogoRelativePath($school);
        if ($defaultRelative !== null) {
            $uri = self::dataUriFromPath(public_path($defaultRelative));
            if ($uri !== null) {
                return $uri;
            }
        }

        return self::dataUriFromPath(public_path('images/pgos_logo.png'));
    }

    /**
     * Public-relative path for the bundled default logo of a school, if any.
     */
    public static function defaultLogoRelativePath(?School $school = null): ?string
    {
        $code = $school?->code;
        if (! is_string($code) || $code === '') {
            return null;
        }

        $logos = config('schools.default_logos', []);
        $path = $logos[$code] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    private static function dataUriFromDisk(string $disk, string $path): ?string
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return null;
        }

        return self::dataUriFromPath($storage->path($path));
    }

    private static function dataUriFromPath(string $absolutePath): ?string
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $mime = mime_content_type($absolutePath) ?: 'image/png';

        // DomPDF does not render WebP; convert to PNG when GD is available.
        if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $image = @imagecreatefromwebp($absolutePath);
            if ($image !== false) {
                ob_start();
                $ok = imagepng($image);
                imagedestroy($image);
                $png = ob_get_clean();

                if ($ok && is_string($png) && $png !== '') {
                    return 'data:image/png;base64,'.base64_encode($png);
                }
            }
        }

        $contents = file_get_contents($absolutePath);

        if ($contents === false || $contents === '') {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
