<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SnapshotBenchmark extends Command
{
    protected $signature = 'benchmark:snapshot
        {phase : "before" or "after" — copies current logs to storage/logs/{phase}/}';

    protected $description = 'Copy current module log files into a before/after snapshot directory for comparison';

    private array $modules = ['auth', 'products', 'orders', 'inventory', 'payments'];

    public function handle(): int
    {
        $phase = $this->argument('phase');

        if (!in_array($phase, ['before', 'after'], true)) {
            $this->error('Phase must be "before" or "after".');
            return Command::FAILURE;
        }

        $targetDir = storage_path("logs/{$phase}");
        File::ensureDirectoryExists($targetDir);

        $copied = 0;

        foreach ($this->modules as $module) {
            $source = storage_path("logs/{$module}.log");
            if (!file_exists($source)) {
                continue;
            }

            $dest = "{$targetDir}/{$module}.log";
            File::copy($source, $dest);
            $copied++;
            $this->line("  Copied: {$module}.log → {$phase}/");
        }

        if ($copied === 0) {
            $this->warn('No log files found in storage/logs/. Run some API requests first.');
            return Command::SUCCESS;
        }

        foreach ($this->modules as $module) {
            $source = storage_path("logs/{$module}.log");
            if (file_exists($source)) {
                File::delete($source);
            }
        }
        $this->line("  Cleared source logs — ready for next test run.");

        $this->newLine();
        $this->info("Snapshot saved to storage/logs/{$phase}/ ({$copied} files).");
        $this->line("Next: run your stress test, then 'php artisan benchmark:snapshot after'.");

        return Command::SUCCESS;
    }
}