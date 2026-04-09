<?php

namespace App\Observers;

use App\Models\RiderReview;
use App\Models\RiderRating;

class RiderReviewObserver
{
    public function created(RiderReview $review)
    {
        $riderRating = RiderRating::firstOrCreate(['rider_id' => $review->rider_id]);
        $riderRating->total_ratings += 1;
        $riderRating->total_performance += $review->performance_rating;
        $riderRating->total_speed += $review->speed_rating;
        $riderRating->total_handling += $review->handling_rating;
        
        $riderRating->avg_performance = $riderRating->total_performance / $riderRating->total_ratings;
        $riderRating->avg_speed = $riderRating->total_speed / $riderRating->total_ratings;
        $riderRating->avg_handling = $riderRating->total_handling / $riderRating->total_ratings;
        $riderRating->overall_rating = ($riderRating->avg_performance + $riderRating->avg_speed + $riderRating->avg_handling) / 3;
        
        $riderRating->save();
    }
    
    // Optional: handle updates and deletions
    public function updated(RiderReview $review)
    {
        // Recalculate from scratch or adjust deltas
    }
    
    public function deleted(RiderReview $review)
    {
        // Decrement totals and recalc averages
    }
}