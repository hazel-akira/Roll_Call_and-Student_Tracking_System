<?php

namespace Tests\Unit\Models;

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolMailFromTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolved_mail_from_prefers_school_column(): void
    {
        config([
            'services.microsoft_graph.mail_from' => 'fallback@example.test',
            'schools.mail_from.PS' => 'ps-env@example.test',
        ]);

        $school = School::query()->create([
            'name' => 'Pioneer School',
            'code' => 'PS',
            'active' => true,
            'mail_from' => 'ps-db@example.test',
        ]);

        $this->assertSame('ps-db@example.test', $school->resolvedMailFrom());
    }

    public function test_resolved_mail_from_uses_config_by_school_code(): void
    {
        config([
            'services.microsoft_graph.mail_from' => 'fallback@example.test',
            'schools.mail_from.PGS' => 'pgs-env@example.test',
        ]);

        $school = School::query()->create([
            'name' => 'Pioneer Girls',
            'code' => 'PGS',
            'active' => true,
            'mail_from' => null,
        ]);

        $this->assertSame('pgs-env@example.test', $school->resolvedMailFrom());
    }

    public function test_resolved_mail_from_falls_back_to_global_graph_from(): void
    {
        config([
            'services.microsoft_graph.mail_from' => 'fallback@example.test',
            'schools.mail_from.PJA' => null,
        ]);

        $school = School::query()->create([
            'name' => 'Pioneer Junior',
            'code' => 'PJA',
            'active' => true,
            'mail_from' => null,
        ]);

        $this->assertSame('fallback@example.test', $school->resolvedMailFrom());
    }
}
