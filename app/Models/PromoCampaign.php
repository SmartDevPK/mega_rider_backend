<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'description', 'percentage', 'balance',
        'starts_at', 'ends_at', 'is_active'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'percentage' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function usages()
    {
        return $this->hasMany(PromoUsage::class);
    }
}