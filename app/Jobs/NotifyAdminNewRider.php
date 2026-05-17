<?php

namespace App\Jobs;

use App\Models\Rider;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class NotifyAdminNewRider implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Rider $rider
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // ---------------------------------
        // Log new rider registration
        // ---------------------------------
        Log::info('New rider registration pending approval', [
            'rider_id' => $this->rider->id,
            'email' => $this->rider->email,
            'phone' => $this->rider->phone,
            'status' => $this->rider->status,
        ]);
    }
}
