<?php

namespace App\Http\Controllers;

use App\Actions\CreateInvoiceAction;
use App\Actions\SendNotificationAction;
use App\Jobs\ProcessInvoiceJob;
use App\Jobs\SendNotificationJob;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class OrderController extends Controller
{
    // add items to cart
    public function createOrder($product_id)
    {
        $product = Product::find($product_id);

        if (!$product) {
            return response()->json([
                'message' => 'product not found'
            ], 404);
        }

        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        // check stock
        if ($product->stock <= 0) {
            return response()->json([
                'message' => 'out of stock'
            ], 400);
        }

        //Find existing pending order


        $order = Order::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        // Create order if not exists

        if (!$order) {

            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total_price' => 0
            ]);
        }


        // Check if product already exists in order


        $existingItem = OrderItems::where('order_id', $order->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existingItem) {

            // increase quantity
            $existingItem->quantity += 1;

            // update item total price
            $existingItem->price =
                $existingItem->quantity * $product->price;

            $existingItem->save();
        } else {

            // create new order item
            OrderItems::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => $product->price
            ]);
        }


        // Update order total price

        $order->total_price += $product->price;

        $order->save();

        return response()->json([
            'message' => 'purchase successful',
            'order_id' => $order->id
        ], 200);
    }


    public function confirmOrderBroken($order_id)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        // find pending order
        $order = Order::where('id', $order_id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'order not found'
            ], 404);
        }

        // get order items
        $items = OrderItems::where('order_id', $order->id)->get();

        if ($items->isEmpty()) {
            return response()->json([
                'message' => 'order is empty'
            ], 400);
        }

        /*
    |--------------------------------------------------------------------------
    | BROKEN VERSION (Intentional Race Condition)
    |--------------------------------------------------------------------------
    */
        $stockChanges = [];
        foreach ($items as $item) {

            $product = Product::find($item->product_id);

            if (!$product) {
                return response()->json([
                    'message' => 'product not found'
                ], 404);
            }

            // stock before
            $stockBefore = $product->stock;

            // check stock
            if ($product->stock < $item->quantity) {
                return response()->json([
                    'message' => 'not enough stock',
                    'product_id' => $product->id
                ], 400);
            }

            // simulate processing delay
            sleep(5);

            // naive stock update
            $product->stock = $product->stock - $item->quantity;
            $product->save();
            $stockChanges[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity_ordered' => $item->quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $product->stock
            ];
        }
        $stockAfter = $product->stock;
        // confirm order
        $order->status = 'paid';

        $order->save();

        return response()->json([
            'message' => 'order confirmed successfully',
            'order_id' => $order->id,
            'status' => $order->status,
            'stock_changes' => $stockChanges
        ], 200);
    }

    public function confirmOrderFixed($order_id)
    {

        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        return DB::transaction(function () use ($order_id, $user) {

            /*
        |--------------------------------------------------------------------------
        | Find Pending Order
        |--------------------------------------------------------------------------
        */

            $order = Order::where('id', $order_id)
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->first();

            if (!$order) {
                return response()->json([
                    'message' => 'order not found'
                ], 404);
            }

            /*
        |--------------------------------------------------------------------------
        | Get Order Items
        |--------------------------------------------------------------------------
        */

            $items = OrderItems::where('order_id', $order->id)->get();

            if ($items->isEmpty()) {
                return response()->json([
                    'message' => 'order is empty'
                ], 400);
            }

            /*
        |--------------------------------------------------------------------------
        | FIXED VERSION USING TRANSACTION + ROW LOCKING
        |--------------------------------------------------------------------------
        */
            $stockChanges = [];
            foreach ($items as $item) {

                // lock product row
                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    return response()->json([
                        'message' => 'product not found'
                    ], 404);
                }

                $stockBefore = $product->stock;

                // validate stock safely
                if ($product->stock < $item->quantity) {

                    return response()->json([
                        'message' => 'not enough stock',
                        'product_id' => $product->id
                    ], 400);
                }

                /*
            |--------------------------------------------------------------------------
            | Safe Stock Update
            |--------------------------------------------------------------------------
            */

                $product->stock =
                    $product->stock - $item->quantity;

                $product->save();
                $stockChanges[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity_ordered' => $item->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $product->stock
                ];
            }

            /*
        |--------------------------------------------------------------------------
        | Confirm Order
        |--------------------------------------------------------------------------
        */
            $stockAfter = $product->stock;
            $order->status = 'paid';

            $order->save();

            return response()->json([
                'message' => 'order confirmed successfully',
                'order_id' => $order->id,
                'status' => $order->status,
                'stock_changes' => $stockChanges
            ], 200);
        });

        return response()->json(
            Order::with('items')->findOrFail($id)
        );

    }

    public function showOrder($order_id)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        $order = Order::with('items.product')
            ->where('id', $order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'order not found'
            ], 404);
        }

        return response()->json([
            'order_id' => $order->id,
            'status' => $order->status,
            'total_price' => $order->total_price,
            'items' => $order->items
        ]);
    }


    public function confirmOrderBrokenAsync($order_id)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $order = Order::where('id', $order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'order not found'], 404);
        }

        $order->status = 'paid';
        $order->save();

        $startTime = microtime(true);

        Log::info("[BROKEN-ASYNC] Starting SYNCHRONOUS invoice for Order #{$order->id}");
        sleep(3); 
        app(CreateInvoiceAction::class)->execute($order);

        Log::info("[BROKEN-ASYNC] Starting SYNCHRONOUS notification for Order #{$order->id}");
        sleep(2); 
        app(SendNotificationAction::class)->execute($order);

        $elapsed = round((microtime(true) - $startTime) * 1000); // ms

        return response()->json([
            'message'         => 'order confirmed (BROKEN - sync blocking)',
            'order_id'        => $order->id,
            'status'          => $order->status,
            'processing_mode' => 'synchronous',
            'response_time_ms'=> $elapsed,
            'note'            => 'User waited for invoice + notification inside the request. Total ~5s.'
        ], 200);
    }

    public function confirmOrderFixedAsync($order_id)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $order = Order::where('id', $order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'order not found'], 404);
        }

        $order->status = 'paid';
        $order->save();

        ProcessInvoiceJob::dispatch($order);
        SendNotificationJob::dispatch($order);

        Log::info("[FIXED-ASYNC] Jobs dispatched for Order #{$order->id}. Response sent immediately.");

        return response()->json([
            'message'         => 'order confirmed (FIXED - async)',
            'order_id'        => $order->id,
            'status'          => $order->status,           
        ], 200);
    }
}

