<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductDailySale extends Model
{
    protected $table = 'product_daily_sales';

    protected $fillable = [
        'product_id',
        'date',
        'quantity_sold',
        'revenue_generated',
        'total_cost',
        'net_profit',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}