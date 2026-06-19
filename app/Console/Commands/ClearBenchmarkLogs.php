<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearBenchmarkLogs extends Command
{
    protected $signature = 'benchmark:clear';

    protected $description = 'Delete all benchmark log files to start fresh';

    private array $files = ['auth', 'products', 'orders', 'inventory', 'payments', 'benchmark'];

    public function handle(): int
    {
        foreach ($this->files as $name) {
            $path = storage_path("logs/{$name}.log");
            if (file_exists($path)) {
                File::delete($path);
                $this->line("  Deleted: {$name}.log");
            }
        }

        foreach (['before', 'after'] as $dir) {
            $dirPath = storage_path("logs/{$dir}");
            if (is_dir($dirPath)) {
                File::cleanDirectory($dirPath);
                $this->line("  Cleared: {$dir}/");
            }
        }

        $this->newLine();
        $this->info('All benchmark logs cleared. Ready for fresh tests.');

        return Command::SUCCESS;
    }
}