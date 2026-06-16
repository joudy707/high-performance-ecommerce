<?php

namespace App\Http\Controllers;
use App\Models\Order;
use App\Models\OrderItems;
use Illuminate\Support\Facades\Cache;
use App\Jobs\GenerateDailySalesReport;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\DailySale;
use App\Models\ProductDailySale;

class SalesController extends Controller
{
    public function brokenReport(){
        $orders = Order::whereDate('created_at', today())
        ->where('status', 'paid')
        ->with('items.product')
        ->get();
        $total_revenue = 0;
        $total_orders = 0;
        $total_items_sold = 0;
        $total_net_profit = 0;
        $products_data = [];

        foreach($orders as $order) {
            $total_orders += 1;
            $total_revenue += $order->total_price;
            foreach ($order->items as $item) {
                $total_items_sold += $item->quantity;
                if (!isset($products_data[$item->product_id])) {
                    $products_data[$item->product_id] = [
                        'name'=> $item->product->name,
                        'quantity_sold' => 0,
                        'revenue'=> 0,
                        'total_cost'=> 0,
                        'net_profit'=> 0
                    ];
                }
                $products_data[$item->product_id]['quantity_sold'] += $item->quantity;
                $products_data[$item->product_id]['revenue']+= $item->price * $item->quantity; 
                $profit = ($item->price - $item->product->cost_price) * $item->quantity;             
                $products_data[$item->product_id]['net_profit'] += $profit;
                $products_data[$item->product_id]['total_cost']+= $item->product->cost_price * $item->quantity;
                $total_net_profit += $profit;
            }
        }
        return response()->json([
            'date'=>today()->toDateString(),
            'summary'=> [
                'total_orders'=> $total_orders,
                'total_revenue'=> round($total_revenue, 2),
                'total_items_sold'=> $total_items_sold,
                'total_net_profit' => round($total_net_profit, 2),
            ],
            'products' => array_map(function($product) {
                return [
                    'name'=> $product['name'],
                    'quantity_sold'=> $product['quantity_sold'],
                    'revenue'=>round($product['revenue'], 2),
                    'total_cost'    => round($product['total_cost'], 2),
                    'net_profit'=>round($product['net_profit'], 2),
                ];
            }, $products_data),
        ]);
    }





public function fixedReport()
{
    $date = today()->toDateString();
    $summary = DailySale::where('date', $date)->first();

    if (!$summary) {
        return response()->json([
            'message'=> 'No report available for today yet.'
        ], 404);
    }

    $products = ProductDailySale::where('date', $date)
        ->with('product:id,name')
        ->get()
        ->map(fn($row) => [
            'name'=> $row->product->name,
            'quantity_sold'=> $row->quantity_sold,
            'revenue'=> round($row->revenue_generated, 2),
            'total_cost'=> round($row->total_cost, 2),
            'net_profit'=> round($row->net_profit, 2),
        ]);

    return response()->json([
        'date'=> $date,
        'summary'=> [
    'total_orders'=> $summary->total_orders,
    'total_revenue'=> round($summary->total_revenue, 2),
    'total_items_sold'=> $summary->total_items_sold,
    'total_net_profit'=> round($summary->total_net_profit, 2),
],
        'products' => $products,
    ]);
}
}