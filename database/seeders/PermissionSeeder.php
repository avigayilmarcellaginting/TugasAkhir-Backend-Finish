<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Define Permissions
        $permissions = [
            // Dashboard Module
            ['slug' => 'dashboard-admin', 'name' => 'Dashboard Admin', 'description' => 'Melihat ringkasan bisnis lengkap'],
            ['slug' => 'dashboard-cashier', 'name' => 'Dashboard Kasir', 'description' => 'Melihat ringkasan penjualan personal'],
            // ['slug' => 'dashboard-staff', 'name' => 'Dashboard Staff', 'description' => 'Melihat informasi tugas & pengumuman'],

            // POS Module
            ['slug' => 'module-pos', 'name' => 'Akses Kasir', 'description' => 'Melakukan transaksi penjualan'],
            // ['slug' => 'action-delete-transaction', 'name' => 'Hapus Transaksi', 'description' => 'Menghapus data transaksi penting'],

            // Inventory Module
            ['slug' => 'module-inventory', 'name' => 'Akses Stok', 'description' => 'Kelola produk dan stok'],

            // Reports Module
            ['slug' => 'module-reports', 'name' => 'Akses Laporan', 'description' => 'Melihat laporan penjualan lengkap'],
            ['slug' => 'view-profit-loss', 'name' => 'Lihat Laba Rugi', 'description' => 'Melihat laporan keuangan sensitif'],

            // Employee/Admin Module
            ['slug' => 'module-employees', 'name' => 'Akses Admin', 'description' => 'Kelola karyawan dan cabang'],

            // Expenses
            ['slug' => 'module-expenses', 'name' => 'Akses Pengeluaran', 'description' => 'Input pengeluaran bisnis'],

            // Marketing
            ['slug' => 'module-marketing', 'name' => 'Akses Pemasaran', 'description' => 'Kelola promo dan marketing'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['slug' => $p['slug']], $p);
        }

        // 2. Assign Permissions to Roles
        $owner = Role::where('slug', 'owner')->first();
        $admin = Role::where('slug', 'admin')->first();
        $cashier = Role::where('slug', 'cashier')->first();

        // Owner/Admin get everything
        $allPermissionIds = Permission::pluck('id')->toArray();
        if ($owner) $owner->permissions()->sync($allPermissionIds);
        if ($admin) $admin->permissions()->sync($allPermissionIds);

        // Cashier restricted: Only POS by default
        $cashierPermissions = Permission::whereIn('slug', [
            'module-pos',
        ])->pluck('id')->toArray();
        if ($cashier) $cashier->permissions()->sync($cashierPermissions);

        // Staff restricted: No permissions by default (Owner must assign)
        $staff = Role::where('slug', 'staff')->first();
        if ($staff) {
            $staff->permissions()->sync([]);
        }
    }
}
