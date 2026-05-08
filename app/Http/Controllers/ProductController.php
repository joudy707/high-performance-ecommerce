<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json(Product::all());
    }


    public function show($product_id)
    {
        $product = Product::find($product_id);
        if (!$product) {
            return response()->json([
                'message' => 'product is not found'
            ], 404);
        }
        return response()->json([
            'product' => $product
        ], 200);
    }

    #product already exists
    public function addToStock(Request $request,$product_id)
    {
        $validator = Validator::make($request->all(), [
            'stock' => 'required|integer|min:1|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $product = Product::find($product_id);

        if (!$product) {
            return response()->json([
                'message' => 'product not found'
            ], 404);
        }

        // add stock
        $product->stock += $request->stock;

        $product->save();

        return response()->json([
            'message' => 'stock updated successfully',
            'new_stock' => $product->stock
        ]);
    }

    public function addProduct(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'price' => 'required|numeric|min:1',
        'stock' => 'required|integer|min:0'
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
    }

    $product = Product::create([
        'name' => $request->name,
        'price' => $request->price,
        'stock' => $request->stock,
    ]);

    return response()->json([
        'message' => 'product created successfully',
        'product' => $product
    ], 201);
}
}
