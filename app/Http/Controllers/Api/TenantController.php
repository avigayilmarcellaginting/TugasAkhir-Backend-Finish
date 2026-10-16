<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use App\Models\Tenant;

class TenantController extends Controller
{
    /**
     * Update the tenant's subscription plan.
     */
    public function upgrade(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->tenant_id) {
            return response()->json(['message' => 'User does not belong to any tenant'], 400);
        }

        // Only owner can upgrade plan
        if (!$user->hasRole('owner')) {
            return response()->json(['message' => 'Only owners can upgrade the subscription plan'], 403);
        }

        $validator = Validator::make($request->all(), [
            'plan' => 'required|string|in:free,pro,business'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid plan selected'], 400);
        }

        $tenant = Tenant::find($user->tenant_id);
        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        $tenant->plan = $request->plan;
        $tenant->save();

        return response()->json([
            'message' => 'Berhasil upgrade ke paket ' . ucfirst($request->plan),
            'tenant' => $tenant
        ]);
    }
}
