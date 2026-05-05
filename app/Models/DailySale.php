<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailySale extends Model
{
    /** @use HasFactory<\Database\Factories\DailySaleFactory> */
    use HasFactory;
    protected $fillable = [
        'date',
        'total_orders',
        'total_revenue',
    ];
}
