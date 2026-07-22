<?php

namespace Tests\Unit\Support;

use App\Models\School;
use App\Support\ReportBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_logo_paths_are_mapped_by_school_code(): void
    {
        $expected = [
            'PS' => 'images/ps.png',
            'PGS' => 'images/pgslogo.webp',
            'PJA' => 'images/pjaogo.png',
            'PGJA' => 'images/pgjalogo.webp',
            'SPTA' => 'images/ST PAULS THOMAS EMBLEM.png',
        ];

        foreach ($expected as $code => $relativePath) {
            $school = School::query()->create([
                'name' => "School {$code}",
                'code' => $code,
                'level' => 'senior',
                'active' => true,
            ]);

            $this->assertSame($relativePath, ReportBranding::defaultLogoRelativePath($school));
            $this->assertFileExists(public_path($relativePath));

            $uri = ReportBranding::logoDataUri($school);
            $this->assertNotNull($uri);
            $this->assertStringStartsWith('data:image/', $uri);
        }
    }

    public function test_uploaded_logo_overrides_default_mapping(): void
    {
        Storage::fake('public');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Storage::disk('public')->put('school-logos/custom.png', $png);

        $school = School::query()->create([
            'name' => 'Pioneer School',
            'code' => 'PS',
            'level' => 'senior',
            'active' => true,
            'logo_path' => 'school-logos/custom.png',
        ]);

        $uri = ReportBranding::logoDataUri($school);

        $this->assertNotNull($uri);
        $this->assertStringContainsString(base64_encode($png), $uri);
    }

    public function test_unknown_school_falls_back_to_pgos_logo(): void
    {
        $school = School::query()->create([
            'name' => 'Other School',
            'code' => 'OTHER',
            'level' => 'senior',
            'active' => true,
        ]);

        $this->assertNull(ReportBranding::defaultLogoRelativePath($school));

        $uri = ReportBranding::logoDataUri($school);
        $fallback = file_get_contents(public_path('images/pgos_logo.png'));

        $this->assertNotNull($uri);
        $this->assertNotFalse($fallback);
        $this->assertStringContainsString(base64_encode($fallback), $uri);
    }

    public function test_logo_absolute_path_resolves_for_mapped_school(): void
    {
        $school = School::query()->create([
            'name' => 'Pioneer School',
            'code' => 'PS',
            'level' => 'senior',
            'active' => true,
        ]);

        $path = ReportBranding::logoAbsolutePath($school);

        $this->assertNotNull($path);
        $this->assertFileExists($path);
    }

    public function test_school_model_exposes_logo_url_for_mapped_code(): void
    {
        $school = School::query()->create([
            'name' => 'Pioneer School',
            'code' => 'PS',
            'level' => 'senior',
            'active' => true,
        ]);

        $this->assertNotNull($school->logo_url);
        $this->assertStringContainsString('ps.png', $school->logo_url);
    }
}
