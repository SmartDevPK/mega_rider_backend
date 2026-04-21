<?php
// app/Listeners/ProcessReferralReward.php

namespace App\Listeners;

use App\Events\OrderDelivered;
use App\Services\ReferralService;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessReferralReward implements ShouldQueue
{
    public function __construct(protected ReferralService $referralService) {}

    public function handle(OrderDelivered $event): void
    {
        $this->referralService->processFirstDeliveryReward($event->order);
    }
}