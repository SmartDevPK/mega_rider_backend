<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'promo_campaign_id',
        'discount_amount',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
    ];

    /*
    |-------------------------
    | Relationships
    |-------------------------
    */

    public function campaign()
    {
        return $this->belongsTo(PromoCampaign::class, 'promo_campaign_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
