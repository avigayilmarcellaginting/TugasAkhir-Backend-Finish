<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['slug' => 'owner', 'name' => 'Owner', 'description' => 'Business Owner'],
            ['slug' => 'admin', 'name' => 'Admin', 'description' => 'Business Administrator'],
            ['slug' => 'cashier', 'name' => 'Cashier', 'description' => 'Store Cashier'],
            ['slug' => 'staff', 'name' => 'Staff', 'description' => 'General Staff / Server'],
        ];

        foreach ($roles as $role) {
            \App\Models\Role::firstOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
