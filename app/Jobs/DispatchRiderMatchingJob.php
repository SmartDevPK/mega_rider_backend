<?php
// app/Jobs/DispatchRiderMatchingJob.php
namespace App\Jobs;

use App\Models\Order;
use App\Models\RideMatching;
use App\Services\RiderMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchRiderMatchingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Order $order;
    protected RideMatching $matching;

    public function __construct(Order $order, RideMatching $matching)
    {
        $this->order = $order;
        $this->matching = $matching;
    }

    public function handle(RiderMatchingService $riderMatching)
    {
        // Implement actual rider matching logic
        $riderMatching->findAndAssignRider($this->order, $this->matching);
    }
}