<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Expense;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function admin(Request $request)
    {
        $branchId = $request->branch_id;
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // --- Today's Revenue ---
        $todayRevenueQuery = Transaction::whereDate('created_at', $today)
            ->where('payment_status', 'paid');
        if ($branchId) $todayRevenueQuery->where('branch_id', $branchId);
        $todayRevenue = (float) $todayRevenueQuery->sum('total_amount');

        // --- Today's Transaction Count ---
        $todayTxQuery = Transaction::whereDate('created_at', $today)
            ->where('payment_status', 'paid');
        if ($branchId) $todayTxQuery->where('branch_id', $branchId);
        $todayTransactions = $todayTxQuery->count();

        // --- Today's COGS ---
        $todayCogs = (float) TransactionDetail::whereHas('transaction', function ($q) use ($today, $branchId) {
            $q->whereDate('created_at', $today)->where('payment_status', 'paid');
            if ($branchId) $q->where('branch_id', $branchId);
        })->sum(DB::raw('quantity * cost_price'));

        // --- Today's Expenses ---
        $todayExpQuery = Expense::whereDate('date', $today);
        if ($branchId) $todayExpQuery->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhereNull('branch_id');
        });
        $todayExpenses = (float) $todayExpQuery->sum('amount');

        // --- Net Profit Today ---
        $todayNetProfit = $todayRevenue - $todayCogs - $todayExpenses;

        // --- Yesterday's Revenue (for comparison) ---
        $yesterdayRevenueQuery = Transaction::whereDate('created_at', $yesterday)
            ->where('payment_status', 'paid');
        if ($branchId) $yesterdayRevenueQuery->where('branch_id', $branchId);
        $yesterdayRevenue = (float) $yesterdayRevenueQuery->sum('total_amount');

        // --- % Change ---
        $revenueChange = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : ($todayRevenue > 0 ? 100 : 0);

        // --- Low Stock Products (stock <= 5, max 5 items) ---
        $lowStockQuery = Product::with('category')
            ->whereHas('stockRecord', function ($q) {
                $q->where('stock', '<=', 5);
            })
            ->where('track_stock', true)
            ->where('is_active', true);

        if ($branchId) {
            $lowStockQuery->where('branch_id', $branchId);
        }

        $lowStockProducts = $lowStockQuery->limit(5)->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'stock' => $p->stock,
                'category' => $p->category?->name,
            ];
        });

        // --- Recent Transactions (last 5) ---
        $recentTxQuery = Transaction::with('user')
            ->where('payment_status', 'paid')
            ->latest();
        if ($branchId) $recentTxQuery->where('branch_id', $branchId);
        $recentTransactions = $recentTxQuery->limit(5)->get()->map(function ($tx) {
            return [
                'id' => $tx->id,
                'code' => $tx->transaction_code,
                'total' => (float) $tx->total_amount,
                'payment_method' => $tx->payment_method,
                'customer_name' => $tx->customer_name,
                'cashier' => $tx->user?->name,
                'time' => $tx->created_at->format('H:i'),
                'date' => $tx->created_at->format('d M'),
            ];
        });

        return response()->json([
            'today_revenue' => $todayRevenue,
            'today_transactions' => $todayTransactions,
            'today_net_profit' => $todayNetProfit,
            'today_expenses' => $todayExpenses,
            'yesterday_revenue' => $yesterdayRevenue,
            'revenue_change' => $revenueChange,
            'low_stock_products' => $lowStockProducts,
            'recent_transactions' => $recentTransactions,
        ]);
    }
}
