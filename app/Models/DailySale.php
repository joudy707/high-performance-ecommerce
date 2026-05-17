<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailySale extends Model
{
    protected $table = 'daily_sales';

    protected $fillable = [
    'date',
    'total_orders',
    'total_revenue',
    'total_items_sold',
    'total_net_profit',
];
}
