<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\DailySale;
use App\Models\ProductDailySale;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessSalesReportChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public array $orderIds) {}
    public function handle(): void
    {
        $orders = Order::whereIn('id', $this->orderIds)
            ->with('items.product')
            ->get();
        $date= today()->toDateString();
        $total_orders= 0;
        $total_revenue = 0;
        $products_data = [];
        $total_items_sold = 0;
        $total_net_profit=0;


        foreach ($orders as $order) {
            $total_orders++;
            $total_revenue += $order->total_price;

            foreach ($order->items as $item) {
                $product_id = $item->product_id;
                $total_items_sold += $item->quantity;

                if (!isset($products_data[$product_id])) {
                    $products_data[$product_id] = [
                        'quantity_sold'=> 0,
                        'revenue'=> 0,
                        'total_cost'=> 0,
                        'net_profit' => 0,
                    ];
                }

                $revenue = $item->price * $item->quantity;
                $cost    = $item->product->cost_price * $item->quantity;

                $products_data[$product_id]['quantity_sold'] += $item->quantity;
                $products_data[$product_id]['revenue']+= $revenue;
                $products_data[$product_id]['total_cost']+= $cost;
                $products_data[$product_id]['net_profit'] += ($revenue - $cost);
                $total_net_profit += ($revenue - $cost);
            }
        }

        
        DailySale::upsert(
    [[
        'date'=> $date,
        'total_orders'=> $total_orders,
        'total_revenue'=> $total_revenue,
        'total_items_sold' => $total_items_sold,
        'total_net_profit'=> $total_net_profit,
    ]],
    ['date'],
    [
        'total_orders'=> DB::raw('total_orders + VALUES(total_orders)'),
        'total_revenue'=> DB::raw('total_revenue + VALUES(total_revenue)'),
        'total_items_sold'=> DB::raw('total_items_sold + VALUES(total_items_sold)'),
        'total_net_profit'=> DB::raw('total_net_profit + VALUES(total_net_profit)'),
    ]
);

        
        foreach ($products_data as $product_id => $data) {
            ProductDailySale::upsert(
                [[
                    'product_id'=> $product_id,
                    'date'=> $date,
                    'quantity_sold'=> $data['quantity_sold'],
                    'revenue_generated'=> $data['revenue'],
                    'total_cost' => $data['total_cost'],
                    'net_profit'=> $data['net_profit'],
                ]],
                ['product_id', 'date'],
                [
                    'quantity_sold'=> DB::raw('quantity_sold + VALUES(quantity_sold)'),
                    'revenue_generated'=> DB::raw('revenue_generated + VALUES(revenue_generated)'),
                    'total_cost'=> DB::raw('total_cost + VALUES(total_cost)'),
                    'net_profit'=> DB::raw('net_profit + VALUES(net_profit)'),
                ]
            );
        }
    }
}