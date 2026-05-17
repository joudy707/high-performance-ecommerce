<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function addToCartBroken(Request $request, $product_id)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'quantity' => 'nullable|integer|min:1|max:100',
        ]);

        $userId = (int) $request->input('user_id');
        $quantity = (int) $request->input('quantity', 1);

        $product = Product::find($product_id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found',
            ], 404);
        }

        if ($product->stock < $quantity) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient stock',
            ], 400);
        }

        $cart = Cart::firstOrCreate(
            ['user_id' => $userId, 'status' => 'pending'],
            ['total_price' => 0]
        );

        $item = CartItem::firstOrNew([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);

        $item->quantity = ($item->quantity ?? 0) + $quantity;
        $item->price = $product->price;
        $item->save();

        $product->decrement('stock', $quantity);
        $cart->increment('total_price', $product->price * $quantity);

        return response()->json([
            'status' => 'success',
            'message' => 'Product added to cart',
        ], 201);
    }

    public function addToCartFixed(Request $request, $product_id)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'quantity' => 'nullable|integer|min:1|max:10',
        ]);

        $userId = (int) $request->input('user_id');
        $quantity = (int) $request->input('quantity', 1);

        try {
            $result = DB::transaction(function () use ($userId, $product_id, $quantity) {
                $product = Product::query()
                    ->select('id', 'price')
                    ->whereKey($product_id)
                    ->first();

                if (!$product) {
                    return [
                        'code' => 404,
                        'body' => [
                            'status' => 'error',
                            'message' => 'Product not found',
                        ],
                    ];
                }

                $updated = Product::query()
                    ->whereKey($product_id)
                    ->where('stock', '>=', $quantity)
                    ->decrement('stock', $quantity);

                if ($updated === 0) {
                    return [
                        'code' => 400,
                        'body' => [
                            'status' => 'error',
                            'message' => 'Insufficient stock',
                        ],
                    ];
                }

                $cart = Cart::firstOrCreate(
                    ['user_id' => $userId, 'status' => 'pending'],
                    ['total_price' => 0]
                );

                $affected = CartItem::query()
                    ->where('cart_id', $cart->id)
                    ->where('product_id', $product_id)
                    ->increment('quantity', $quantity, [
                        'price' => $product->price,
                        'updated_at' => now(),
                    ]);

                if ($affected === 0) {
                    try {
                        CartItem::create([
                            'cart_id' => $cart->id,
                            'product_id' => $product_id,
                            'quantity' => $quantity,
                            'price' => $product->price,
                        ]);
                    } catch (\Illuminate\Database\QueryException $e) {
                        CartItem::query()
                            ->where('cart_id', $cart->id)
                            ->where('product_id', $product_id)
                            ->increment('quantity', $quantity, [
                                'price' => $product->price,
                                'updated_at' => now(),
                            ]);
                    }
                }

                $cart->increment('total_price', $product->price * $quantity);

                return [
                    'code' => 201,
                    'body' => [
                        'status' => 'success',
                        'message' => 'Product added safely',
                    ],
                ];
            }, 3);

            return response()->json($result['body'], $result['code']);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add product safely',
            ], 500);
        }
    }
}
