<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\OrderDelivered;
use App\Listeners\ProcessReferralReward;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderDelivered::class => [
            ProcessReferralReward::class,
        ],
    ];
}
