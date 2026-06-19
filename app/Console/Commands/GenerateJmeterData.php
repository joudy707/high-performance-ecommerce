<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GenerateJmeterData extends Command
{
    protected $signature = 'jmeter:generate';

    protected $description = 'Generates 100 pristine test users, orders, and products for JMeter stress testing';

    public function handle()
    {
        $csvPath = 'C:\Users\VISION\Desktop\jmeterTest_broken\jmeterTest_broken\users.csv';

        if (file_exists($csvPath)) {
            unlink($csvPath);
        }

        $file = fopen($csvPath, 'w');
        fputcsv($file, ['email', 'password', 'order_id', 'product_id', 'user_id']);

        $count = 0;
        $password = 'password123';
        $hashedPassword = Hash::make($password);
        $now = now();

        $this->info('Generating 100 pristine test users, orders, and products. Please wait...');

        DB::transaction(function () use ($file, &$count, $password, $hashedPassword, $now) {
            for ($i = 1; $i <= 100; $i++) {
                $email = "stresstest_{$now->timestamp}_{$i}@example.com";
                $userId = DB::table('users')->insertGetId([
                    'name' => "Stress Test User {$i}",
                    'email' => $email,
                    'password' => $hashedPassword,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $productId = DB::table('products')->insertGetId([
                    'name' => "Stress Test Product {$i}",
                    'price' => 50.00,
                    'stock' => 100,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $orderId = DB::table('orders')->insertGetId([
                    'user_id' => $userId,
                    'status' => 'pending',
                    'total_price' => 50.00,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'quantity' => 1,
                    'price' => 50.00,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                fputcsv($file, [$email, $password, $orderId, $productId, $userId]);
                $count++;
            }
        });

        fclose($file);
        $this->info("Success! {$count} new records generated and saved to users.csv.");
    }
}
