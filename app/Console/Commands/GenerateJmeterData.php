<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GenerateJmeterData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jmeter:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates 100 pristine test users, orders, and products for JMeter stress testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $csvPath = 'C:/Users/Haidar/Desktop/jmeterTest/users.csv';

        // Clear old file
        if (file_exists($csvPath)) {
            unlink($csvPath);
        }

        // Open file and write the headers
        $file = fopen($csvPath, 'w');
        fputcsv($file, ['email', 'password', 'order_id', 'product_id', 'user_id']);

        $count = 0;
        $password = 'password123';
        $hashedPassword = Hash::make($password);
        $now = now();

        $this->info('Generating 100 pristine test users, orders, and products. Please wait...');

        // We wrap this in a transaction so it runs incredibly fast
        DB::transaction(function () use ($file, &$count, $password, $hashedPassword, $now) {
            for ($i = 1; $i <= 100; $i++) {
                
                // 1. Create a brand new User
                $email = "stresstest_{$now->timestamp}_{$i}@example.com";
                $userId = DB::table('users')->insertGetId([
                    'name' => "Stress Test User {$i}",
                    'email' => $email,
                    'password' => $hashedPassword,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // 2. Create a brand new Product
                $productId = DB::table('products')->insertGetId([
                    'name' => "Stress Test Product {$i}",
                    'price' => 50.00,
                    'stock' => 100,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // 3. Create a brand new Order
                $orderId = DB::table('orders')->insertGetId([
                    'user_id' => $userId,
                    'status' => 'pending',
                    'total_price' => 50.00,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // 4. Link the Product to the Order
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'quantity' => 1,
                    'price' => 50.00,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // 5. Write the sorted row straight into the CSV
                fputcsv($file, [$email, $password, $orderId, $productId, $userId]);
                $count++;
            }
        });

        fclose($file);
        $this->info("Success! {$count} new records generated and saved to users.csv.");
    }
}