<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoUsage extends Model
{
    protected $table = 'promo_usages';

    protected $fillable = [
        'order_id',
        'promo_campaign_id',
        'discount_amount',
    ];
}
