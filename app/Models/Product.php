<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'category_id',
        'name',
        'sku',
        'price',
        'cost_price',
        'description',
        'image',
        'is_active',
        'track_stock'
    ];

    protected $appends = ['stock'];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_active' => 'boolean',
        'track_stock' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockRecord()
    {
        return $this->hasOne(ProductBranch::class);
    }

    public function getStockAttribute()
    {
        return $this->stockRecord?->stock ?? 0;
    }
}
