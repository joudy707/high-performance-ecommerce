<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductSeeder extends Seeder
{
    /**
     * This larger dataset is intentional for the resource-management demo.
     * With only 100 products, the unoptimized endpoint is too cheap and the
     * benefit of SQL filtering + LIMIT is not visible.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('cart_items')->truncate();
        DB::table('carts')->truncate();
        DB::table('order_items')->truncate();
        DB::table('orders')->truncate();
        DB::table('products')->truncate();

        Schema::enableForeignKeyConstraints();

        $now = now();
        $total = 20000;
        $batchSize = 1000;

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $rows = [];

            for ($i = 1; $i <= $batchSize; $i++) {
                $n = $offset + $i;
                $rows[] = [
                    'name' => 'product ' . $n . ' phone pro new',
                    'price' => random_int(10, 500),
                    'stock' => 100000,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('products')->insert($rows);
        }
    }
}
