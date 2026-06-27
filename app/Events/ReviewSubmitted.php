<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\RiderReview;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewSubmitted
{
  use Dispatchable, SerializesModels;

  public function __construct(public RiderReview $review) {}
}
