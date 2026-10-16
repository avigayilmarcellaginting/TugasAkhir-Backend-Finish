<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    protected $subscriptionService;

    public function __construct(\App\Services\SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function index(Request $request)
    {
        $query = Transaction::with(['user', 'details.product', 'promotion'])->latest();

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|string',
            'cash_amount' => 'nullable|numeric|min:0',
            'branch_id' => 'required|exists:branches,id',
            'customer_name' => 'nullable|string|max:255',
            'table_number' => 'nullable|string|max:50',
            'customer_phone' => 'nullable|string|max:20',
            'dining_option' => 'required|in:dine_in,take_away',
            'promotion_id' => 'nullable|exists:promotions,id',
        ]);

        return DB::transaction(function () use ($request) {
            $user = $request->user();

            // Allow if user is not associated with a tenant (super admin?) or check tenant limits
            if ($user->tenant && !$this->subscriptionService->canCreateTransaction($user->tenant)) {
                abort(403, 'Transaction limit reached for this month. Please upgrade your plan.');
            }

            // Enforce branch_id for non-owners
            // Check if user has 'owner' or 'admin' role. 
            // Assuming we can check roles via $user->hasRole or similar, or just check if branch_id is set on user.
            // For safety, if user has a branch_id, force it.
            if ($user->branch_id) {
                $request->merge(['branch_id' => $user->branch_id]);
            }

            $totalAmount = 0;
            $itemsToInsert = [];

            // Calculate total and prepare details
            foreach ($request->items as $item) {
                $product = Product::with('stockRecord')->find($item['product_id']);

                // Check stock availability
                if ($product->track_stock) {
                    $currentStock = $product->stockRecord?->stock ?? 0;
                    if ($currentStock < $item['quantity']) {
                        $formattedStock = (float) $currentStock;
                        abort(400, "Stok produk '{$product->name}' tidak mencukupi. Sisa stok: {$formattedStock}");
                    }
                }

                $subtotal = $product->price * $item['quantity'];
                $totalAmount += $subtotal;

                $itemsToInsert[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'cost_price' => $product->cost_price ?: 0, // [NEW] Snapshot HPP
                    'subtotal' => $subtotal,
                ];
            }

            $subtotal = $totalAmount;
            $discountAmount = 0;
            $promotionId = $request->promotion_id;

            if ($promotionId) {
                $promotion = Promotion::find($promotionId);

                // Basic validation
                if (!$promotion->is_active) {
                    abort(400, 'Promo tidak aktif.');
                }
                if (now()->lt($promotion->start_date) || now()->gt($promotion->end_date)) {
                    abort(400, 'Promo sudah kadaluarsa atau belum dimulai.');
                }
                if ($promotion->min_purchase && $subtotal < $promotion->min_purchase) {
                    abort(400, 'Minimum pembelian tidak terpenuhi.');
                }

                // Branch validation
                if ($promotion->branch_id && $promotion->branch_id != $request->branch_id) {
                    abort(400, 'Promo tidak berlaku untuk cabang ini.');
                }

                // Calculate discount
                if ($promotion->type === 'percentage') {
                    $discountAmount = $subtotal * ($promotion->value / 100);
                } else {
                    $discountAmount = $promotion->value;
                }

                // Max discount validation
                $discountAmount = min($discountAmount, $subtotal);
            }

            $totalAmount = $subtotal - $discountAmount;

            // Calculate change if cash
            $cashAmount = $request->cash_amount ?? 0;
            $changeAmount = 0;
            if ($request->payment_method === 'cash') {
                if ($cashAmount < $totalAmount) {
                    abort(400, 'Insufficient cash amount');
                }
                $changeAmount = $cashAmount - $totalAmount;
            }

            // Find active shift
            $shift = Shift::where('user_id', $user->id)
                ->where('branch_id', $request->branch_id)
                ->where('status', 'open')
                ->first();

            // Create Transaction
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'branch_id' => $request->branch_id,
                'shift_id' => $shift ? $shift->id : null, // Link to shift
                'transaction_code' => Transaction::generateCode(),
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'promotion_id' => $promotionId,
                'total_amount' => $totalAmount,
                'cash_amount' => $cashAmount,
                'change_amount' => $changeAmount,
                'payment_method' => $request->payment_method,
                'payment_status' => 'paid',
                'customer_name' => $request->customer_name,
                'table_number' => $request->table_number,
                'customer_phone' => $request->customer_phone,
                'dining_option' => $request->dining_option,
                'notes' => $request->notes,
            ]);

            // Update Shift Cash Sales
            if ($shift && $request->payment_method === 'cash') {
                $shift->increment('cash_sales', $totalAmount);
            }

            // Create Details
            foreach ($itemsToInsert as $detail) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $detail['product_id'],
                    'quantity' => $detail['quantity'],
                    'price' => $detail['price'],
                    'cost_price' => $detail['cost_price'], // [NEW]
                    'subtotal' => $detail['subtotal'],
                ]);

                // Update Stock
                $product = Product::with('stockRecord')->find($detail['product_id']);
                if ($product->track_stock && $product->stockRecord) {
                    $product->stockRecord->decrement('stock', $detail['quantity']);
                }
            }

            return response()->json([
                'message' => 'Transaction successful',
                'transaction' => $transaction->load(['details.product', 'user', 'branch', 'promotion'])
            ], 201);
        });
    }

    public function show($id)
    {
        $transaction = Transaction::with(['user', 'details.product', 'branch', 'promotion'])->findOrFail($id);
        return response()->json($transaction);
    }
}
