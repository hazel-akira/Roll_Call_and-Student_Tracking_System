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
        $path = self::resolvedLogoAbsolutePath($school);

        return $path === null ? null : self::dataUriFromPath($path);
    }

    /**
     * Absolute filesystem path for Excel drawings / offline tooling.
     * WebP sources are converted to a temporary PNG when GD is available.
     */
    public static function logoAbsolutePath(?School $school = null): ?string
    {
        $path = self::resolvedLogoAbsolutePath($school);

        if ($path === null) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        if ($mime !== 'image/webp') {
            return $path;
        }

        if (! function_exists('imagecreatefromwebp')) {
            return null;
        }

        $image = @imagecreatefromwebp($path);
        if ($image === false) {
            return null;
        }

        $temp = tempnam(sys_get_temp_dir(), 'school-logo-');
        if ($temp === false) {
            imagedestroy($image);

            return null;
        }

        $pngPath = $temp.'.png';
        @unlink($temp);

        $ok = imagepng($image, $pngPath);
        imagedestroy($image);

        return $ok ? $pngPath : null;
    }

    /**
     * Public URL for UI report headers (upload → default by school code → PGOS).
     */
    public static function logoPublicUrl(?School $school = null): ?string
    {
        if ($school?->logo_path) {
            $storage = Storage::disk('public');
            if ($storage->exists($school->logo_path)) {
                return url($storage->url($school->logo_path));
            }
        }

        $defaultRelative = self::defaultLogoRelativePath($school);
        if ($defaultRelative !== null && is_file(public_path($defaultRelative))) {
            return asset($defaultRelative);
        }

        if (is_file(public_path('images/pgos_logo.png'))) {
            return asset('images/pgos_logo.png');
        }

        return null;
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

    private static function resolvedLogoAbsolutePath(?School $school = null): ?string
    {
        if ($school?->logo_path) {
            $storage = Storage::disk('public');
            if ($storage->exists($school->logo_path)) {
                return $storage->path($school->logo_path);
            }
        }

        $defaultRelative = self::defaultLogoRelativePath($school);
        if ($defaultRelative !== null) {
            $absolute = public_path($defaultRelative);
            if (is_file($absolute) && is_readable($absolute)) {
                return $absolute;
            }
        }

        $fallback = public_path('images/pgos_logo.png');

        return is_file($fallback) && is_readable($fallback) ? $fallback : null;
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
