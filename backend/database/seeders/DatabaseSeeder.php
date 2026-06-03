<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(SchoolAndClassSeeder::class);
        $this->call(SchoolUserSeeder::class);

        $adminEmail = env('SEED_ADMIN_EMAIL');

        if (! $adminEmail) {
            return;
        }

        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();

        User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => env('SEED_ADMIN_NAME', 'Platform Administrator'),
                'role_id' => $adminRole->id,
                'status' => 'active',
                'password' => Str::random(32),
            ],
        );
    }
}
