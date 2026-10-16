<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Branch;
use App\Models\Transaction;

class SubscriptionService
{
    /**
     * Get plan limits based on the plan name.
     */
    public function getLimits(string $plan): array
    {
        $plan = strtoupper($plan);
        $plans = [
            'FREE' => [
                'max_users' => 2,
                'max_branches' => 1,
                'max_transactions' => 200,
                'features' => [
                    'pos_basic' => true,
                    'inventory' => true,
                    'reports_basic' => true,
                    'multi_branch' => false,
                    'export_excel' => false,
                ],
                'allowed_permissions' => [
                    'dashboard-admin',
                    'dashboard-cashier',
                    'dashboard-staff',
                    'module-pos',
                    'module-inventory',
                    'module-reports',
                    'module-employees',
                ]
            ],
            'PRO' => [
                'max_users' => 5,
                'max_branches' => 3,
                'max_transactions' => -1, // unlimited
                'features' => [
                    'pos_basic' => true,
                    'inventory' => true,
                    'reports_basic' => true,
                    'reports_full' => true,
                    'multi_branch' => true,
                    'export_excel' => true,
                    'support_whatsapp' => true,
                ],
                'allowed_permissions' => [
                    'dashboard-admin',
                    'dashboard-cashier',
                    'dashboard-staff',
                    'module-pos',
                    'action-delete-transaction',
                    'module-inventory',
                    'module-reports',
                    'view-profit-loss',
                    'module-employees',
                    'module-expenses',
                    'module-marketing',
                    'export-excel',
                ]
            ],
            'BUSINESS' => [
                'max_users' => -1, // unlimited
                'max_branches' => -1, // unlimited
                'max_transactions' => -1, // unlimited
                'features' => [
                    'pos_basic' => true,
                    'inventory' => true,
                    'reports_basic' => true,
                    'reports_full' => true,
                    'multi_branch' => true,
                    'export_excel' => true,
                    'support_whatsapp' => true,
                    // 'multi_warehouse' => true,
                    'priority_support' => true,
                ],
                'allowed_permissions' => [
                    'dashboard-admin',
                    'dashboard-cashier',
                    'dashboard-staff',
                    'module-pos',
                    'action-delete-transaction',
                    'module-inventory',
                    'module-reports',
                    'view-profit-loss',
                    'module-employees',
                    'module-expenses',
                    'module-marketing',
                    'export-excel',
                ]
            ],
        ];

        return $plans[$plan] ?? $plans['FREE'];
    }

    /**
     * Check if a permission is allowed for a tenant's plan.
     */
    public function isPermissionAllowed(Tenant $tenant, string $permissionSlug): bool
    {
        $limits = $this->getLimits($tenant->plan);
        return in_array($permissionSlug, $limits['allowed_permissions'] ?? []);
    }

    /**
     * Filter a list of permissions based on tenant's plan.
     */
    public function filterPermissions(Tenant $tenant, array $permissionSlugs): array
    {
        $limits = $this->getLimits($tenant->plan);
        $allowed = $limits['allowed_permissions'] ?? [];

        return array_values(array_intersect($permissionSlugs, $allowed));
    }

    /**
     * Check if a tenant can add a new user.
     */
    public function canAddUser(Tenant $tenant): bool
    {
        $limits = $this->getLimits($tenant->plan);
        if ($limits['max_users'] === -1) {
            return true;
        }

        return $tenant->users()->count() < $limits['max_users'];
    }

    /**
     * Check if a tenant can add a new branch.
     */
    public function canAddBranch(Tenant $tenant): bool
    {
        $limits = $this->getLimits($tenant->plan);
        if ($limits['max_branches'] === -1) {
            return true;
        }

        // Assuming a relationship exists or we can query branches
        return Branch::where('tenant_id', $tenant->id)->count() < $limits['max_branches'];
    }

    /**
     * Check if a tenant can create a new transaction.
     */
    public function canCreateTransaction(Tenant $tenant): bool
    {
        $limits = $this->getLimits($tenant->plan);
        if ($limits['max_transactions'] === -1) {
            return true;
        }

        // Query via branch since transaction doesn't have tenant_id directly
        $count = Transaction::whereHas('branch', function ($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id);
        })
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        return $count < $limits['max_transactions'];
    }
}
