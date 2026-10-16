<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BranchController extends Controller
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Branch::where('tenant_id', $user->tenant_id);

        if (!$user->isOwnerOrAdmin()) {
            $query->where('id', $user->branch_id);
        }

        $branches = $query->get();
        return response()->json($branches);
    }

    public function store(Request $request)
    {
        $user = auth('api')->user();
        $tenant = $user->tenant;

        // Check plan limits
        if (!$this->subscriptionService->canAddBranch($tenant)) {
            return response()->json([
                'error' => 'Limit reached',
                'message' => 'Your current plan (' . $tenant->plan . ') limits you to ' .
                    $this->subscriptionService->getLimits($tenant->plan)['max_branches'] . ' branch(es).'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => $request->get('name'),
            'address' => $request->get('address'),
            'phone' => $request->get('phone'),
        ]);

        return response()->json([
            'message' => 'Branch successfully created',
            'branch' => $branch
        ], 201);
    }
}
