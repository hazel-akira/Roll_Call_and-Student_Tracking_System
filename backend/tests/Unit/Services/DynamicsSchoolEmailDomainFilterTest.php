<?php

namespace Tests\Unit\Services;

use App\Services\DynamicsService;
use ReflectionMethod;
use Tests\TestCase;

class DynamicsSchoolEmailDomainFilterTest extends TestCase
{
    public function test_filters_students_to_allowed_email_domains_for_girls_junior_academy(): void
    {
        config([
            'schools.dynamics_names' => [
                'PGJA' => 'Pioneer Girls Junior Academy',
            ],
            'schools.email_domains' => [
                'PGJA' => ['pioneergirlsjunioracademy.co.ke'],
            ],
        ]);

        $service = app(DynamicsService::class);
        $method = new ReflectionMethod($service, 'applySchoolEmailDomainFilter');
        $method->setAccessible(true);

        $filtered = $method->invoke($service, [
            [
                'email' => 'girl@pioneergirlsjunioracademy.co.ke',
                'first_name' => 'Girl',
            ],
            [
                'email' => 'boy@pioneerjunioracademy.co.ke',
                'first_name' => 'Boy',
            ],
            [
                'email' => null,
                'first_name' => 'NoEmail',
            ],
        ], 'Pioneer Girls Junior Academy');

        $this->assertCount(2, $filtered);
        $this->assertSame('girl@pioneergirlsjunioracademy.co.ke', $filtered[0]['email']);
        $this->assertSame('NoEmail', $filtered[1]['first_name']);
    }

    public function test_leaves_students_unfiltered_when_school_has_no_domain_rules(): void
    {
        config([
            'schools.dynamics_names' => [
                'XX' => 'Example School',
            ],
            'schools.email_domains' => [],
        ]);

        $service = app(DynamicsService::class);
        $method = new ReflectionMethod($service, 'applySchoolEmailDomainFilter');
        $method->setAccessible(true);

        $students = [
            ['email' => 'anyone@example.test', 'first_name' => 'Any'],
        ];

        $filtered = $method->invoke($service, $students, 'Example School');

        $this->assertSame($students, $filtered);
    }
}
