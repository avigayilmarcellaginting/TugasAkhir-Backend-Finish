<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBranch extends Model
{
    protected $fillable = [
        'product_id',
        'stock'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
