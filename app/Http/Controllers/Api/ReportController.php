<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Expense;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function profitAndLoss(Request $request)
    {
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();
        $branchId = $request->branch_id;

        // 1. Revenue (Total Sales)
        $revenueQuery = Transaction::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('payment_status', 'paid');

        if ($branchId) $revenueQuery->where('branch_id', $branchId);
        $revenue = $revenueQuery->sum('total_amount');

        // 2. COGS (HPP)
        $cogsQuery = TransactionDetail::whereHas('transaction', function ($q) use ($startDate, $endDate, $branchId) {
            $q->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->where('payment_status', 'paid');
            if ($branchId) $q->where('branch_id', $branchId);
        });
        // Calculate COGS: sum(quantity * cost_price)
        $cogs = $cogsQuery->sum(DB::raw('quantity * cost_price'));

        // 3. Gross Profit
        $grossProfit = $revenue - $cogs;

        // 4. Expenses
        $expenseQuery = Expense::whereBetween('date', [$startDate, $endDate]);
        if ($branchId) $expenseQuery->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhereNull('branch_id');
        });
        $totalExpenses = $expenseQuery->sum('amount');
        $expensesBreakdown = $expenseQuery->select('category', DB::raw('sum(amount) as total'))
            ->groupBy('category')
            ->get();

        // 5. Net Profit
        $netProfit = $grossProfit - $totalExpenses;

        return response()->json([
            'period' => "{$startDate} to {$endDate}",
            'revenue' => (float)$revenue,
            'cogs' => (float)$cogs,
            'gross_profit' => (float)$grossProfit,
            'expenses' => (float)$totalExpenses,
            'expense_breakdown' => $expensesBreakdown,
            'net_profit' => (float)$netProfit
        ]);
    }

    public function balanceSheet(Request $request)
    {
        // Simple Balance Sheet for now
        // Assets = Cash in Hand + Inventory Value
        // Liabilities = 0 (for now)
        // Equity = Assets - Liabilities

        $branchId = $request->branch_id;

        // 1. Cash (This is tricky without a full ledger, we'll approximate with Total Sales - Expenses)
        // ideally we sum up all transaction cash_amount + other payments - expenses
        // For accurate cash flow, we need to track cash movements from Day 1.
        // Here we'll just sum all VALID transactions and subtract all expenses.

        $totalSalesQuery = Transaction::where('payment_status', 'paid');
        $totalExpenseQuery = Expense::query();

        if ($branchId) {
            $totalSalesQuery->where('branch_id', $branchId);
            $totalExpenseQuery->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        $totalSales = $totalSalesQuery->sum('total_amount');
        $totalExpenses = $totalExpenseQuery->sum('amount');
        $cashInHand = $totalSales - $totalExpenses;

        // 2. Inventory Value (Asset)
        // Sum of (current stock * cost_price)
        // We need a way to get current stock value. 
        // This requires joining products and product_branches (if branch specific) or stock_records.
        // Assuming we have ProductStock or similar.
        // Let's look at Product model to see how stock is stored.
        // Based on previous code: $product->stockRecord->stock
        // We need to sum(stock * cost_price) for all products.

        $inventoryValue = DB::table('products')
            ->join('product_stocks', 'products.id', '=', 'product_stocks.product_id')
            ->when($branchId, function ($q) use ($branchId) {
                return $q->where('product_stocks.branch_id', $branchId);
            })
            ->sum(DB::raw('product_stocks.stock * products.cost_price'));

        $assets = [
            'cash' => $cashInHand,
            'inventory' => $inventoryValue
        ];

        $totalAssets = $cashInHand + $inventoryValue;
        $totalLiabilities = 0;
        $equity = $totalAssets - $totalLiabilities;

        return response()->json([
            'assets' => $assets,
            'total_assets' => (float)$totalAssets,
            'liabilities' => (float)$totalLiabilities,
            'equity' => (float)$equity
        ]);
    }

    public function cashFlow(Request $request)
    {
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();
        $branchId = $request->branch_id;

        // Operating Activities
        // Inflow: Sales
        $cashInQuery = Transaction::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('payment_status', 'paid');
        if ($branchId) $cashInQuery->where('branch_id', $branchId);
        $cashFromSales = $cashInQuery->sum('total_amount');

        // Outflow: Expenses
        $cashOutQuery = Expense::whereBetween('date', [$startDate, $endDate]);
        if ($branchId) $cashOutQuery->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhereNull('branch_id');
        });
        $cashForExpenses = $cashOutQuery->sum('amount');

        $netCashFlow = $cashFromSales - $cashForExpenses;

        return response()->json([
            'period' => "{$startDate} to {$endDate}",
            'operating_activities' => [
                'cash_in_sales' => (float)$cashFromSales,
                'cash_out_expenses' => (float)$cashForExpenses,
                'net_cash_flow' => (float)$netCashFlow
            ],
            'investing_activities' => [],
            'financing_activities' => [],
            'net_increase_cash' => (float)$netCashFlow
        ]);
    }

    public function shiftLog(Request $request)
    {
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->endOfMonth()->toDateString();
        $branchId = $request->branch_id;

        $query = Shift::with(['user', 'branch', 'transactions']) // Eager load transactions
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status', 'closed')
            ->latest();

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $shifts = $query->get()->map(function ($shift) {
            $transactions = $shift->transactions;
            $totalDiscount = $transactions->sum('discount_amount');
            $netSales = $transactions->sum('total_amount');
            $grossSales = $netSales + $totalDiscount;

            return [
                'id' => $shift->id,
                'staff_name' => $shift->user->name,
                'branch_name' => $shift->branch ? $shift->branch->name : 'Pusat',
                'start_time' => $shift->start_time ? $shift->start_time->format('d M Y H:i') : '-',
                'end_time' => $shift->end_time ? $shift->end_time->format('d M Y H:i') : '-',
                'starting_cash' => (float) $shift->starting_cash,
                'cash_sales' => (float) $shift->cash_sales,
                'gross_sales' => (float) $grossSales,
                'total_discount' => (float) $totalDiscount,
                'net_sales' => (float) $netSales,
                'expected_ending_cash' => (float) ($shift->starting_cash + $shift->cash_sales),
                'actual_ending_cash' => (float) $shift->actual_ending_cash,
                'variance' => (float) ($shift->actual_ending_cash - ($shift->starting_cash + $shift->cash_sales)),
                'notes' => $shift->notes,
            ];
        });

        return response()->json($shifts);
    }
}
