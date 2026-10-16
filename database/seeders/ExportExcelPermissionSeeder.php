<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class ExportExcelPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Permission::firstOrCreate(
            ['slug' => 'export-excel'],
            ['name' => 'Export Excel', 'description' => 'Export data to Excel']
        );
    }
}
