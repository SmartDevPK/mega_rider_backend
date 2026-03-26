<?php
// app/Policies/OrderPolicy.php

namespace App\Policies;

use App\Models\User;
use App\Models\Order;

class OrderPolicy
{
    public function assignVehicle(User $user, Order $order)
    {
        // Only the customer who owns the order can assign a vehicle
        return $user->id === $order->customer_id;
    }
    
    public function processPayment(User $user, Order $order)
    {
        // Only the customer who owns the order can process payment
        return $user->id === $order->customer_id;
    }
    
    public function addTip(User $user, Order $order)
    {
        // Only the customer who owns the order can add tip
        return $user->id === $order->customer_id;
    }
    
    public function triggerEmergency(User $user, Order $order)
    {
        // Both customer and driver can trigger emergency
        return $user->id === $order->customer_id || $user->id === $order->driver_id;
    }
}