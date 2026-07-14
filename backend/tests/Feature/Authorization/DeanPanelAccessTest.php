<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Support\RoleSlugs;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesWithJwt;
use Tests\TestCase;

class DeanPanelAccessTest extends TestCase
{
    use AuthenticatesWithJwt;
    use RefreshDatabase;

    public function test_dean_of_students_can_access_dean_panel(): void
    {
        $dean = $this->createUserWithRole(RoleSlugs::DEAN_OF_STUDENTS);
        $panel = Filament::getPanel('dean');

        $this->assertTrue($dean->canAccessPanel($panel));
    }

    public function test_deputy_dean_can_access_dean_panel(): void
    {
        $deputy = $this->createUserWithRole(RoleSlugs::DEPUTY_DEAN);
        $panel = Filament::getPanel('dean');

        $this->assertTrue($deputy->canAccessPanel($panel));
    }

    public function test_teacher_cannot_access_dean_panel(): void
    {
        $teacher = $this->createUserWithRole(RoleSlugs::TEACHER);
        $panel = Filament::getPanel('dean');

        $this->assertFalse($teacher->canAccessPanel($panel));
    }

    public function test_dean_cannot_access_admin_panel(): void
    {
        $dean = $this->createUserWithRole(RoleSlugs::DEAN_OF_STUDENTS);
        $panel = Filament::getPanel('admin');

        $this->assertFalse($dean->canAccessPanel($panel));
    }

    public function test_admin_can_access_dean_panel_for_support(): void
    {
        $admin = $this->createUserWithRole(RoleSlugs::ADMIN);
        $panel = Filament::getPanel('dean');

        $this->assertTrue($admin->canAccessPanel($panel));
    }
}
