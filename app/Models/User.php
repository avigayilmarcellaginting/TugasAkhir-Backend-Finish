<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\Permission;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\Role;
use App\Services\SubscriptionService;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'is_active',
        'tenant_id',
        'branch_id',
        'work_start_at',
        'work_end_at',
    ];

    protected $appends = ['permission_list'];

    public function getPermissionListAttribute()
    {
        return $this->getAllPermissions();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The roles that belong to the user.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function isOwnerOrAdmin(): bool
    {
        return $this->roles()->whereIn('slug', ['owner', 'admin'])->exists();
    }

    /**
     * Get permissions directly assigned to the user.
     */
    public function directPermissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_user', 'user_id', 'permission_id');
    }

    public function getAllPermissions(): array
    {
        // 1. Check for owner/admin role specifically by slug
        $isOwnerOrAdmin = $this->roles()->whereIn('slug', ['owner', 'admin'])->exists();

        if ($isOwnerOrAdmin) {
            // Baseline: Owners/Admins get ALL permissions defined in the system
            $permissions = Permission::pluck('slug')->toArray();
        } else {
            // Baseline: Staff/Cashiers get role-based + direct permissions
            $rolePermissions = DB::table('permissions')
                ->join('permission_role', 'permissions.id', '=', 'permission_role.permission_id')
                ->join('role_user', 'permission_role.role_id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $this->id)
                ->pluck('slug')
                ->toArray();

            $directPermissions = DB::table('permissions')
                ->join('permission_user', 'permissions.id', '=', 'permission_user.permission_id')
                ->where('permission_user.user_id', $this->id)
                ->pluck('slug')
                ->toArray();

            $permissions = array_unique(array_merge($rolePermissions, $directPermissions));
        }

        // 2. Filter EVERYTHING based on Subscription Plan
        $subscriptionService = app(SubscriptionService::class);
        return $subscriptionService->filterPermissions($this->tenant, $permissions);
    }

    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->getAllPermissions());
    }
}
