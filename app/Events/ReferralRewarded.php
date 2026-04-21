<?php
// app/Events/ReferralRewarded.php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ReferralRewarded implements ShouldBroadcastNow
{
    public function __construct(
        public User $referrer,
        public User $referred,
        public float $amount,
        public int $points
    ) {}

    public function broadcastOn()
    {
        return new PrivateChannel("user.{$this->referrer->id}");
    }

    public function broadcastWith()
    {
        return [
            'referrer_id' => $this->referrer->id,
            'referred_user_id' => $this->referred->id,
            'amount' => $this->amount,
            'points' => $this->points,
            'type' => 'first_delivery',
            'message' => "You earned {$this->amount} for referring a friend who completed their first delivery!",
        ];
    }
}