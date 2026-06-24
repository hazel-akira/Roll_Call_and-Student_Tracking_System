<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RepairMigrationStateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_marks_existing_schema_migrations_as_applied(): void
    {
        Artisan::call('migrate', ['--force' => true]);

        $this->assertTrue(Schema::hasTable('schools'));

        $connection = Schema::getConnection();
        $connection->table('migrations')->where('migration', '2026_05_28_100000_create_schools_table')->delete();
        Schema::dropIfExists('school_user');
        $connection->table('migrations')->where('migration', '2026_05_28_100200_create_school_user_table')->delete();

        $this->assertDatabaseMissing('migrations', [
            'migration' => '2026_05_28_100000_create_schools_table',
        ]);

        Artisan::call('migrate:repair', ['--force' => true]);

        $this->assertDatabaseHas('migrations', [
            'migration' => '2026_05_28_100000_create_schools_table',
        ]);

        $exitCode = Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertTrue(Schema::hasTable('school_user'));
    }
}
