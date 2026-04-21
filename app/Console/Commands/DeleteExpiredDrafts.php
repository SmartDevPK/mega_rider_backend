<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class DeleteExpiredDrafts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'drafts:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete draft orders older than 24 hours';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subHours(24);

        $deleted = Order::query()
            ->where('status', 'draft')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Draft cleanup completed. Deleted {$deleted} record(s).");

        return self::SUCCESS;
    }
}
