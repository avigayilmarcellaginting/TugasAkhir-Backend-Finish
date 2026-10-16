<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Permission;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = User::where('tenant_id', $user->tenant_id)->with(['roles', 'branch', 'directPermissions']);

        if (!$user->isOwnerOrAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        $users = $query->get();
        return response()->json($users);
    }

    public function store(Request $request)
    {
        $admin = $request->user();
        if (!$admin->isOwnerOrAdmin()) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'Only owners can manage users.'], 403);
        }
        $tenant = $admin->tenant;

        // Check plan limits
        if (!$this->subscriptionService->canAddUser($tenant)) {
            return response()->json([
                'error' => 'Limit reached',
                'message' => 'Your current plan (' . $tenant->plan . ') limits you to ' .
                    $this->subscriptionService->getLimits($tenant->plan)['max_users'] . ' user(s).'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|exists:roles,slug',
            'branch_id' => 'required|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Verify branch belongs to tenant
        $branch = Branch::where('id', $request->branch_id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$branch) {
            return response()->json(['error' => 'Invalid branch selected'], 403);
        }

        $user = User::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'password' => Hash::make($request->get('password')),
            'work_start_at' => $request->get('work_start_at'),
            'work_end_at' => $request->get('work_end_at'),
        ]);

        $role = Role::where('slug', $request->get('role'))->first();
        $user->roles()->attach($role->id);

        if ($request->has('permissions')) {
            $permissionIds = Permission::whereIn('slug', $request->get('permissions'))->pluck('id');
            $user->directPermissions()->sync($permissionIds);
        }

        return response()->json([
            'message' => 'User successfully created',
            'user' => $user->load('roles', 'directPermissions', 'branch')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $admin = $request->user();
        $user = User::where('id', $id)
            ->where('tenant_id', $admin->tenant_id)
            ->with(['roles', 'directPermissions', 'branch'])
            ->firstOrFail();

        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin->isOwnerOrAdmin()) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'Only owners can manage users.'], 403);
        }

        $user = User::where('id', $id)
            ->where('tenant_id', $admin->tenant_id)
            ->firstOrFail();

        if ($admin->id === $user->id) {
            return response()->json(['error' => 'Action prohibited', 'message' => 'You cannot manage your own account from here.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role' => 'required|exists:roles,slug',
            'branch_id' => 'required|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Verify branch belongs to tenant
        $branch = Branch::where('id', $request->branch_id)
            ->where('tenant_id', $admin->tenant_id)
            ->first();

        if (!$branch) {
            return response()->json(['error' => 'Invalid branch selected'], 403);
        }

        $user->update([
            'branch_id' => $branch->id,
            'name' => $request->get('name'),
            'email' => $request->get('email'),
            'work_start_at' => $request->get('work_start_at'),
            'work_end_at' => $request->get('work_end_at'),
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->get('password'))]);
        }

        // Sync Role
        $role = Role::where('slug', $request->get('role'))->first();
        $user->roles()->sync([$role->id]);

        // Sync Direct Permissions
        if ($request->has('permissions')) {
            $permissionIds = Permission::whereIn('slug', $request->get('permissions'))->pluck('id');
            $user->directPermissions()->sync($permissionIds);
        }

        return response()->json([
            'message' => 'User successfully updated',
            'user' => $user->load('roles', 'directPermissions', 'branch')
        ]);
    }

    public function getPermissions(Request $request)
    {
        $tenant = $request->user()->tenant;
        $permissions = Permission::all();

        $subscriptionService = app(SubscriptionService::class);
        $allowedSlugs = $subscriptionService->getLimits($tenant->plan)['allowed_permissions'] ?? [];

        $filtered = $permissions->filter(function ($permission) use ($allowedSlugs) {
            return in_array($permission->slug, $allowedSlugs);
        })->values();

        return response()->json($filtered);
    }
}
