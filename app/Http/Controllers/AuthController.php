<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Branch;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Gagal masuk. Periksa email dan password Anda.'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $userData = $user->load('tenant', 'roles', 'branch');

        return response()->json([
            'token_type' => 'Bearer',
            'user' => $userData
        ])->cookie('access_token', $token, 60 * 24, '/', null, true, true, false, 'None');
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'business_name' => 'required|string|max:255',
            'business_email' => 'required|string|email|max:255|unique:tenants,email',
            'business_phone' => 'nullable|string|max:20',
            'business_address' => 'nullable|string',
            'plan' => 'required|in:FREE,PRO,BUSINESS',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        return DB::transaction(function () use ($request) {
            // 1. Create Tenant
            $tenant = Tenant::create([
                'name' => $request->get('business_name'),
                'email' => $request->get('business_email'),
                'phone' => $request->get('business_phone'),
                'address' => $request->get('business_address'),
                'plan' => $request->get('plan'),
            ]);

            // 3. Create Default Branch (Pusat)
            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Pusat',
                'address' => $tenant->address,
                'phone' => $tenant->phone,
                'status' => 'active',
            ]);

            // 4. Create User (Owner)
            $user = User::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'password' => Hash::make($request->get('password')),
            ]);

            // 5. Ensure Owner role exists and assign it
            $ownerRole = Role::firstOrCreate(
                ['slug' => 'owner'],
                ['name' => 'Owner', 'description' => 'Business Owner']
            );
            $user->roles()->attach($ownerRole->id);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'User and Business successfully registered',
                'user' => $user->load('tenant', 'roles', 'branch'),
                'token_type' => 'Bearer',
            ], 201)->cookie('access_token', $token, 60 * 24, '/', null, true, true, false, 'None');
        });
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('roles', 'tenant', 'branch');
        return response()->json($user);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out'])
            ->cookie('access_token', '', -1, '/', null, true, true, false, 'None');
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->load('roles', 'tenant', 'branch')
        ]);
    }
}
