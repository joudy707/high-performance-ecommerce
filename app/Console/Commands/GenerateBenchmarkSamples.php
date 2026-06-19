<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateBenchmarkSamples extends Command
{
    protected $signature = 'benchmark:generate-samples
        {--target=logs/before : Target directory under storage/logs/}
        {--requests=100 : Number of sample requests per module}';

    protected $description = 'Generate sample benchmark log entries for testing the analysis tool';

    private array $modules = ['auth', 'products', 'orders', 'inventory', 'payments'];

    private array $scenarios = [
        'auth' => [
            ['POST', 'api/register', 45, 120],
            ['POST', 'api/login', 30, 90],
        ],
        'products' => [
            ['GET',  'api/products', 20, 60],
            ['GET',  'api/products/{id}', 10, 30],
            ['POST', 'api/products', 50, 150],
            ['GET',  'api/products-search', 80, 350],
            ['GET',  'api/cache/top-products', 5, 15],
            ['GET',  'api/cache/product/{id}', 3, 10],
        ],
        'orders' => [
            ['POST', 'api/order/{product_id}', 60, 180],
            ['POST', 'api/orders/{order_id}/confirm-fixed', 150, 500],
            ['POST', 'api/orders/{order_id}/confirm-broken', 3000, 6000],
            ['GET',  'api/orders/{order_id}', 15, 40],
        ],
        'inventory' => [
            ['POST', 'api/cart/{product_id}/add-fixed', 40, 100],
            ['POST', 'api/cart/{product_id}/add-broken', 35, 95],
            ['POST', 'api/products/{product_id}/stock', 25, 70],
        ],
        'payments' => [
            ['POST', 'api/orders/{order_id}/confirm-fixed-distributed', 3000, 5000],
            ['POST', 'api/orders/{order_id}/confirm-fixed-acid', 200, 600],
        ],
    ];

    public function handle(): int
    {
        $targetDir = storage_path('logs/' . $this->option('target'));
        File::ensureDirectoryExists($targetDir);

        $requestsPerModule = (int) $this->option('requests');

        $this->info("Generating $requestsPerModule sample requests per module...");
        $this->newLine();

        foreach ($this->modules as $module) {
            $lines = [];
            $endpoints = $this->scenarios[$module] ?? [];

            if (empty($endpoints)) {
                continue;
            }

            for ($i = 0; $i < $requestsPerModule; $i++) {
                $endpoint = $endpoints[array_rand($endpoints)];
                [$method, $uri, $minMs, $maxMs] = $endpoint;

                $duration = round($minMs + mt_rand() / mt_getrandmax() * ($maxMs - $minMs), 2);
                $status = (mt_rand(0, 100) < 90) ? 200 : 500;
                $requestId = (string) Str::uuid();

                $lines[] = sprintf(
                    '[%s] [%s] [%s] [%s] [%.2f] [%d] [%s]',
                    now()->subMinutes(mt_rand(0, 60))->toIso8601String(),
                    strtoupper($module),
                    $uri,
                    $method,
                    $duration,
                    $status,
                    $requestId
                );
            }

            $filePath = $targetDir . "/{$module}.log";
            File::put($filePath, implode("\n", $lines) . "\n");
            $this->line("  Created: $filePath (" . count($lines) . " entries)");
        }

        $this->newLine();
        $this->info('Sample data generated successfully.');
        $this->line('Run php artisan benchmark:analyze to analyze.');

        return Command::SUCCESS;
    }
}