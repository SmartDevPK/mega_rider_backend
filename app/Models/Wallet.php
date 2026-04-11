<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $table = 'wallets';

    protected $fillable = [
        'customer_id',
        'wallet_balance'
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
