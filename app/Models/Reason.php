<?php
// app/Models/Reason.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reason extends Model
{
    protected $fillable = [
        'type', 'reason', 'sort_order', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Scopes for clean querying
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}