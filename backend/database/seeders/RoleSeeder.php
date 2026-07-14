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
            [
                'name' => 'Dean of Students',
                'slug' => 'dean_of_students',
                'description' => 'Senior staff who manage weekly duty rosters, report recipients, and view attendance reports for assigned schools.',
            ],
            [
                'name' => 'Deputy Dean',
                'slug' => 'deputy_dean',
                'description' => 'Deputy dean with the same roll call management and reporting access as the dean of students.',
            ],
        ], ['slug'], ['name', 'description']);
    }
}
