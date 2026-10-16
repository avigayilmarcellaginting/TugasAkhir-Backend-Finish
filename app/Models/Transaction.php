<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'branch_id',
        'shift_id',
        'transaction_code',
        'total_amount',
        'cash_amount',
        'change_amount',
        'payment_method',
        'payment_status',
        'customer_name',
        'table_number',
        'customer_phone',
        'dining_option',
        'notes',
        'promotion_id',
        'discount_amount',
        'subtotal',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'cash_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function details()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public static function generateCode()
    {
        return 'TRX-' . date('YmdHis') . '-' . rand(100, 999);
    }
}
