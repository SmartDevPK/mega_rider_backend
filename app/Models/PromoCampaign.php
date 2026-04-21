<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'is_active',
        'percentage',
        'balance',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'percentage' => 'decimal:2',
        'balance' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /*
    |-------------------------
    | Relationships
    |-------------------------
    */

    public function usages()
    {
        return $this->hasMany(PromoUsage::class);
    }
}
