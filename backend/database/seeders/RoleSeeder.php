<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::query()->upsert([
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Platform administrators with reporting and user management access.',
            ],
            [
                'name' => 'Teacher',
                'slug' => 'teacher',
                'description' => 'Teachers who can create attendance sessions and manage student roll call.',
            ],
            [
                'name' => 'ICT Staff',
                'slug' => 'ict_staff',
                'description' => 'Support staff who manage integrations, technical operations, and audit visibility.',
            ],
        ], ['slug'], ['name', 'description']);
    }
}
