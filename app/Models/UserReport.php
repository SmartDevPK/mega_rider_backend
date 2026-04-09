<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserReport extends Model
{
    use HasFactory;

    protected $table = 'user_reports';

    protected $fillable = [
        'reporter_id',
        'reported_id',
        'reason_id',
        'comment',
    ];

    // Relations
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reported()
    {
        return $this->belongsTo(User::class, 'reported_id');
    }

    public function reason()
    {
        return $this->belongsTo(ReportReason::class, 'reason_id');
    }
}
