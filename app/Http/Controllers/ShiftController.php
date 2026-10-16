<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ShiftController extends Controller
{
    public function current(Request $request)
    {
        $user = $request->user();
        $shift = Shift::where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->where('status', 'open')
            ->withCount('transactions')
            ->first();

        if (!$shift) {
            return response()->json(null);
        }

        // Calculate financials for this shift
        $transactions = $shift->transactions; // Assuming hasMany relationship exists
        $totalDiscount = $transactions->sum('discount_amount');
        $netSales = $transactions->sum('total_amount'); // Total after discount
        $grossSales = $netSales + $totalDiscount; // Total before discount

        $shift->total_discount = $totalDiscount;
        $shift->gross_sales = $grossSales;
        $shift->net_sales = $netSales;

        // Ensure expected_ending_cash is up to date (Starting + Cash Sales)
        // cash_sales is incremented on transaction creation, so it should be correct.
        // But let's recalculate from transactions just in case (optional, but safer?)
        // actually, increment is atomic, let's trust it unless we want to sum cash payment transactions.
        // Let's stick to the stored cash_sales for now, but we can also display recalculation if needed.

        return response()->json($shift);
    }

    public function start(Request $request)
    {
        $request->validate([
            'starting_cash' => 'required|numeric|min:0',
        ]);

        // Check if already has an open shift
        $existing = Shift::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Anda sudah memiliki shift yang aktif.'], 422);
        }

        $user = $request->user();

        // Calculate lateness
        $lateness = 0;
        if ($user->work_start_at) {
            $now = Carbon::now();
            $scheduledStart = Carbon::now()->setTimeFromTimeString($user->work_start_at);

            if ($now->greaterThan($scheduledStart)) {
                $lateness = (int) $now->diffInMinutes($scheduledStart);
            }
        }

        $shift = Shift::create([
            'user_id' => $user->id,
            'branch_id' => $user->branch_id,
            'tenant_id' => $user->tenant_id,
            'start_time' => Carbon::now(),
            'starting_cash' => $request->starting_cash,
            'expected_ending_cash' => $request->starting_cash,
            'status' => 'open',
            'scheduled_start' => $user->work_start_at,
            'scheduled_end' => $user->work_end_at,
            'lateness_minutes' => $lateness,
        ]);

        return response()->json([
            'message' => 'Shift berhasil dimulai',
            'shift' => $shift
        ]);
    }

    public function end(Request $request)
    {
        $request->validate([
            'actual_ending_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        $user = $request->user();
        $shift = Shift::where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->where('status', 'open')
            ->first();

        if (!$shift) {
            // Check if any shift exists at all for this user
            $anyShift = Shift::where('user_id', $user->id)->exists();
            $message = $anyShift
                ? "Shift aktif tidak ditemukan untuk user ini. Silakan muat ulang halaman."
                : "Anda belum pernah memulai shift.";

            return response()->json(['message' => $message], 404);
        }

        $shift->update([
            'end_time' => Carbon::now(),
            'actual_ending_cash' => $request->actual_ending_cash,
            'notes' => $request->notes,
            'status' => 'closed'
        ]);

        return response()->json([
            'message' => 'Shift berhasil diakhiri',
            'shift' => $shift
        ]);
    }
}
