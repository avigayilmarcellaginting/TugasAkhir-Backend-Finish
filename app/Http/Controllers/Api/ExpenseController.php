<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Expense::with('branch', 'user')->latest();

        // Enforce branch for non-owners/admins
        if ($user->branch_id && !$user->hasRole('owner') && !$user->hasRole('admin')) {
            $query->where('branch_id', $user->branch_id);

            // Also hide expenses created by Owners/Admins
            $query->whereHas('user', function ($q) {
                $q->whereHas('roles', function ($r) {
                    $r->whereNotIn('slug', ['owner', 'admin']);
                });
            });
        } elseif (($user->hasRole('owner') || $user->hasRole('admin')) && $request->has('branch_id') && $request->branch_id) {
            // For owners/admins, filter by requested branch or show all
            $query->where('branch_id', $request->branch_id);
        } elseif (($user->hasRole('owner') || $user->hasRole('admin')) && (!$request->has('branch_id') || !$request->branch_id)) {
            // Show all if no branch selected (or explicitly null/empty)
            // No additional where clause needed for "all"
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Enforce branch_id for non-owners/admins
        if ($user->branch_id && !$user->hasRole('owner') && !$user->hasRole('admin')) {
            $request->merge(['branch_id' => $user->branch_id]);
        }

        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string',
        ]);

        $validated['user_id'] = $user->id;

        $expense = Expense::create($validated);

        return response()->json([
            'message' => 'Expense recorded successfully',
            'expense' => $expense
        ], 201);
    }

    public function update(Request $request, $id)
    {
        // Restrict to Owner/Admin
        if (!$request->user()->hasRole('owner') && !$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $expense = Expense::findOrFail($id);

        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string',
        ]);

        $expense->update($validated);

        return response()->json([
            'message' => 'Expense updated successfully',
            'expense' => $expense
        ]);
    }

    public function destroy(Request $request, $id)
    {
        // Prevent deletion for everyone (Edit only policy)
        return response()->json(['message' => 'Deleting expenses is not allowed. Please edit instead.'], 403);
    }
}
