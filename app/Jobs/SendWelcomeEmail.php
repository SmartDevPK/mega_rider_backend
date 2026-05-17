<?php

namespace App\Jobs;

use App\Models\Rider;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use App\Mail\RiderWelcomeMail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendWelcomeEmail implements ShouldQueue
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
        // Send welcome email to rider
        Mail::to($this->rider->email)
            ->send(new RiderWelcomeMail($this->rider));
    }
}
