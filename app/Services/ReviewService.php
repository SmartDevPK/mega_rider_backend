<?php

namespace App\Services;

use App\Models\Order;
use App\Models\RiderReview;
use App\Models\RiderRating;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    /**
     * Submit a rider review.
     *
     * Steps:
     * 1. Lock order for update
     * 2. Validate rider assignment
     * 3. Validate order delivery
     * 4. Prevent duplicate reviews
     * 5. Create review
     * 6. Update rider rating aggregates
     * 7. Fire review submitted event
     */
    public function submitReview(array $validated, int $customerId): array
    {
        return DB::transaction(function () use ($validated, $customerId) {

            // 1 Lock and get order
            $orde = Order::where('order_id', $validated['order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            //  Ensure a rider is assigned
            if (!$order->rider_id) {
                throw ValidationException::withMessages([
                    'order_id' => 'No rider assigned to this order.'
                ]);
            }

            //  Ensure order is delivered
            if ($order->status !== 'delivered') {
                throw ValidationException::withMessages([
                    'order_id' => 'You can only review completed orders.'
                ]);
            }

            //  Check for duplicate reviews
            $alreadyReviewed = RiderReview::where('order_id', $order->order_id)
                ->where('customer_id', $customerId)
                ->exists();

            if ($alreadyReviewed) {
                throw ValidationException::withMessages([
                    'order_id' => 'You have already reviewed this order.'
                ]);
            }

            // Create review
            $review = RiderReview::create([
                'order_id'           => $order->order_id,
                'rider_id'           => $order->rider_id,
                'customer_id'        => $customerId,
                'performance_rating' => $validated['performance_rating'],
                'speed_rating'       => $validated['speed_rating'],
                'handling_rating'    => $validated['handling_rating'],
                'review_content'     => $validated['review_content'] ?? null,
            ]);

            //  Update rider rating aggregates
            $this->updateRiderRating(
                $order->rider_id,
                $validated['performance_rating'],
                $validated['speed_rating'],
                $validated['handling_rating']
            );

            //  Fire review submitted event
            event(new \App\Events\ReviewSubmitted($review));

            return [
                'order_id' => $order->order_id,
                'average_rating' => $review->average_rating,
            ];
        });
    }

    /**
     * Update rider rating aggregates after a review.
     */
    protected function updateRiderRating(int $riderId, int $performance, int $speed, int $handling): void
    {
        $riderRating = RiderRating::firstOrCreate(
            ['rider_id' => $riderId],
            [
                'total_ratings'     => 0,
                'total_performance' => 0,
                'total_speed'       => 0,
                'total_handling'    => 0,
                'avg_performance'   => 0,
                'avg_speed'         => 0,
                'avg_handling'      => 0,
                'overall_rating'    => 0,
            ]
        );

        // Increment counters
        $riderRating->increment('total_ratings');
        $riderRating->increment('total_performance', $performance);
        $riderRating->increment('total_speed', $speed);
        $riderRating->increment('total_handling', $handling);

        // Refresh to recalculate averages
        $riderRating->refresh();

        $riderRating->avg_performance = $riderRating->total_performance / $riderRating->total_ratings;
        $riderRating->avg_speed       = $riderRating->total_speed / $riderRating->total_ratings;
        $riderRating->avg_handling    = $riderRating->total_handling / $riderRating->total_ratings;

        $riderRating->overall_rating = round((
            $riderRating->avg_performance +
            $riderRating->avg_speed +
            $riderRating->avg_handling
        ) / 3, 2);

        $riderRating->save();
    }

    /**
     * Delete a review and update rider rating aggregates.
     */
    public function deleteReview(RiderReview $review): void
    {
        DB::transaction(function () use ($review) {

            $riderRating = RiderRating::where('rider_id', $review->rider_id)
                ->lockForUpdate()
                ->first();

            if ($riderRating) {
                $riderRating->decrement('total_ratings');
                $riderRating->decrement('total_performance', $review->performance_rating);
                $riderRating->decrement('total_speed', $review->speed_rating);
                $riderRating->decrement('total_handling', $review->handling_rating);

                $riderRating->refresh();

                if ($riderRating->total_ratings > 0) {
                    $riderRating->avg_performance = $riderRating->total_performance / $riderRating->total_ratings;
                    $riderRating->avg_speed       = $riderRating->total_speed / $riderRating->total_ratings;
                    $riderRating->avg_handling    = $riderRating->total_handling / $riderRating->total_ratings;

                    $riderRating->overall_rating = round((
                        $riderRating->avg_performance +
                        $riderRating->avg_speed +
                        $riderRating->avg_handling
                    ) / 3, 2);
                } else {
                    $riderRating->update([
                        'avg_performance' => 0,
                        'avg_speed'       => 0,
                        'avg_handling'    => 0,
                        'overall_rating'  => 0,
                    ]);
                }

                $riderRating->save();
            }

            $review->delete();
        });
    }
}
