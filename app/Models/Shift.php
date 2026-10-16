<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'user_id',
        'branch_id',
        'tenant_id',
        'start_time',
        'end_time',
        'starting_cash',
        'cash_sales',
        'expected_ending_cash',
        'actual_ending_cash',
        'status',
        'notes',
        'scheduled_start',
        'scheduled_end',
        'lateness_minutes'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'starting_cash' => 'decimal:2',
        'cash_sales' => 'decimal:2',
        'expected_ending_cash' => 'decimal:2',
        'actual_ending_cash' => 'decimal:2',
        'lateness_minutes' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
