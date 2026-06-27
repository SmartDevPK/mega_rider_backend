<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\RiderReview;
use App\Models\RiderRating;
use App\Events\ReviewSubmitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * ReviewService
 * 
 * Handles rider review operations including:
 * - Submitting reviews for completed orders
 * - Updating and deleting reviews
 * - Aggregating rider ratings
 * - Preventing duplicate reviews
 */
class ReviewService
{
    // =========================================================================
    // CONSTANTS
    // =========================================================================

    private const MAX_RATING = 5;
    private const MIN_RATING = 1;
    private const DEFAULT_PER_PAGE = 15;

    // =========================================================================
    // MAIN PUBLIC METHODS
    // =========================================================================

    /**
     * Submit a rider review
     * 
     * Steps:
     * 1. Lock order for update
     * 2. Validate rider assignment
     * 3. Validate order delivery
     * 4. Prevent duplicate reviews
     * 5. Create review
     * 6. Update rider rating aggregates
     * 7. Fire review submitted event
     *
     * @throws ValidationException
     */
    public function submitReview(array $validated, int $customerId): array
    {
        return DB::transaction(function () use ($validated, $customerId) {
            // 1. Lock and get order
            $order = $this->getOrderForUpdate($validated['order_id']);

            // 2. Validate rider assignment
            $this->validateRiderAssigned($order);

            // 3. Validate order is delivered
            $this->validateOrderDelivered($order);

            // 4. Check for duplicate reviews
            $this->validateNoDuplicateReview($order, $customerId);

            // 5. Create the review
            $review = $this->createReview($order, $validated, $customerId);

            // 6. Update rider rating aggregates
            $this->updateRiderRating($order->rider_id, $review);

            // 7. Fire review submitted event
            event(new ReviewSubmitted($review));

            Log::info('Review submitted successfully', [
                'review_id' => $review->id,
                'order_id' => $order->order_id,
                'rider_id' => $order->rider_id,
                'customer_id' => $customerId,
                'average_rating' => $review->average_rating,
            ]);

            return [
                'order_id' => $order->order_id,
                'review_id' => $review->id,
                'average_rating' => $review->average_rating,
                'message' => 'Review submitted successfully',
            ];
        });
    }

    /**
     * Delete a review and update rider rating aggregates
     */
    public function deleteReview(RiderReview $review): bool
    {
        return DB::transaction(function () use ($review) {
            $riderId = $review->rider_id;

            // Get rider rating record
            $riderRating = $this->getRiderRatingForUpdate($riderId);

            if ($riderRating) {
                $this->decrementRiderRatings($riderRating, $review);
                $this->recalculateRiderAverages($riderRating);
                $riderRating->save();
            }

            // Delete the review
            $review->delete();

            Log::info('Review deleted successfully', [
                'review_id' => $review->id,
                'rider_id' => $riderId,
                'order_id' => $review->order_id,
            ]);

            return true;
        });
    }

    /**
     * Update an existing review
     *
     * @throws ValidationException
     */
    public function updateReview(RiderReview $review, array $validated): RiderReview
    {
        return DB::transaction(function () use ($review, $validated) {
            // Store old ratings
            $oldRatings = $this->getReviewRatings($review);

            // Update the review
            $review->update([
                'performance_rating' => $validated['performance_rating'],
                'speed_rating' => $validated['speed_rating'],
                'handling_rating' => $validated['handling_rating'],
                'communication_rating' => $validated['communication_rating'] ?? $review->communication_rating,
                'review_content' => $validated['review_content'] ?? $review->review_content,
            ]);

            // Recalculate average rating
            $review->average_rating = $review->calculateAverageRating();
            $review->markAsEdited();
            $review->save();

            // Adjust rider ratings
            $this->adjustRiderRatings(
                $review->rider_id,
                $oldRatings,
                $this->getReviewRatings($review)
            );

            Log::info('Review updated successfully', [
                'review_id' => $review->id,
                'rider_id' => $review->rider_id,
                'old_avg' => $oldRatings['average'],
                'new_avg' => $review->average_rating,
            ]);

            return $review->fresh();
        });
    }

    /**
     * Get average rating for a specific rider
     */
    public function getRiderAverageRating(int $riderId): ?float
    {
        $riderRating = RiderRating::where('rider_id', $riderId)->first();

        return $riderRating?->overall_rating;
    }

    /**
     * Get all reviews for a specific rider with pagination
     */
    public function getRiderReviews(int $riderId, int $perPage = self::DEFAULT_PER_PAGE)
    {
        return RiderReview::where('rider_id', $riderId)
            ->with(['customer' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'profile_picture');
            }])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get reviews by customer with pagination
     */
    public function getCustomerReviews(int $customerId, int $perPage = self::DEFAULT_PER_PAGE)
    {
        return RiderReview::where('customer_id', $customerId)
            ->with(['rider' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'profile_picture', 'rating');
            }])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get review by order ID
     */
    public function getReviewByOrder(string $orderId): ?RiderReview
    {
        return RiderReview::where('order_id', $orderId)
            ->with(['customer', 'rider'])
            ->first();
    }

    /**
     * Check if a customer has reviewed a specific order
     */
    public function hasReviewed(string $orderId, int $customerId): bool
    {
        return RiderReview::where('order_id', $orderId)
            ->where('customer_id', $customerId)
            ->exists();
    }

    /**
     * Get rating statistics for a rider
     */
    public function getRiderRatingStats(int $riderId): array
    {
        $reviews = RiderReview::where('rider_id', $riderId)
            ->where('is_approved', true)
            ->get();

        $totalReviews = $reviews->count();

        if ($totalReviews === 0) {
            return [
                'total_reviews' => 0,
                'average_rating' => 0,
                'rating_distribution' => [
                    5 => 0,
                    4 => 0,
                    3 => 0,
                    2 => 0,
                    1 => 0,
                ],
                'recent_reviews' => [],
            ];
        }

        // Calculate rating distribution
        $distribution = [
            5 => $reviews->where('average_rating', '>=', 4.5)->count(),
            4 => $reviews->whereBetween('average_rating', [3.5, 4.49])->count(),
            3 => $reviews->whereBetween('average_rating', [2.5, 3.49])->count(),
            2 => $reviews->whereBetween('average_rating', [1.5, 2.49])->count(),
            1 => $reviews->where('average_rating', '<', 1.5)->count(),
        ];

        return [
            'total_reviews' => $totalReviews,
            'average_rating' => round($reviews->avg('average_rating'), 2),
            'rating_distribution' => $distribution,
            'recent_reviews' => $reviews->take(5)->map(function ($review) {
                return [
                    'id' => $review->id,
                    'average_rating' => $review->average_rating,
                    'review_content' => $review->review_summary,
                    'customer_name' => $review->customer?->full_name,
                    'created_at' => $review->created_at->diffForHumans(),
                ];
            }),
        ];
    }

    // =========================================================================
    // VALIDATION METHODS
    // =========================================================================

    /**
     * Get order with lock for update
     * 
     * @throws ValidationException
     */
    private function getOrderForUpdate(string $orderId): Order
    {
        $order = Order::where('order_id', $orderId)
            ->lockForUpdate()
            ->first();

        if (!$order) {
            throw ValidationException::withMessages([
                'order_id' => ['Order not found.'],
            ]);
        }

        return $order;
    }

    /**
     * Validate that a rider is assigned to the order
     * 
     * @throws ValidationException
     */
    private function validateRiderAssigned(Order $order): void
    {
        if (!$order->rider_id) {
            throw ValidationException::withMessages([
                'order_id' => ['No rider was assigned to this order.'],
            ]);
        }
    }

    /**
     * Validate that the order is delivered
     * 
     * @throws ValidationException
     */
    private function validateOrderDelivered(Order $order): void
    {
        if ($order->status !== Order::STATUS_DELIVERED) {
            throw ValidationException::withMessages([
                'order_id' => ['You can only review completed orders.'],
            ]);
        }
    }

    /**
     * Validate no duplicate review exists
     * 
     * @throws ValidationException
     */
    private function validateNoDuplicateReview(Order $order, int $customerId): void
    {
        $alreadyReviewed = RiderReview::where('order_id', $order->order_id)
            ->where('customer_id', $customerId)
            ->exists();

        if ($alreadyReviewed) {
            throw ValidationException::withMessages([
                'order_id' => ['You have already reviewed this order.'],
            ]);
        }
    }

    /**
     * Validate rating values
     * 
     * @throws ValidationException
     */
    private function validateRating(int $rating, string $field): void
    {
        if ($rating < self::MIN_RATING || $rating > self::MAX_RATING) {
            throw ValidationException::withMessages([
                $field => ["Rating must be between " . self::MIN_RATING . " and " . self::MAX_RATING . "."],
            ]);
        }
    }

    // =========================================================================
    // REVIEW CREATION & UPDATES
    // =========================================================================

    /**
     * Create a new review
     */
    private function createReview(Order $order, array $validated, int $customerId): RiderReview
    {
        $review = RiderReview::create([
            'order_id' => $order->order_id,
            'rider_id' => $order->rider_id,
            'customer_id' => $customerId,
            'performance_rating' => $validated['performance_rating'],
            'speed_rating' => $validated['speed_rating'],
            'handling_rating' => $validated['handling_rating'],
            'communication_rating' => $validated['communication_rating'] ?? null,
            'review_content' => $validated['review_content'] ?? null,
            'is_approved' => true, // Auto-approve, could be changed based on moderation
        ]);

        // Calculate and save average rating
        $review->average_rating = $review->calculateAverageRating();
        $review->save();

        return $review;
    }

    /**
     * Get review ratings as array
     */
    private function getReviewRatings(RiderReview $review): array
    {
        return [
            'performance' => $review->performance_rating,
            'speed' => $review->speed_rating,
            'handling' => $review->handling_rating,
            'communication' => $review->communication_rating,
            'average' => $review->average_rating,
        ];
    }

    // =========================================================================
    // RIDER RATING AGGREGATION
    // =========================================================================

    /**
     * Get rider rating record with lock for update
     */
    private function getRiderRatingForUpdate(int $riderId): ?RiderRating
    {
        return RiderRating::where('rider_id', $riderId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Update rider rating aggregates after a new review
     */
    private function updateRiderRating(int $riderId, RiderReview $review): void
    {
        $riderRating = RiderRating::firstOrCreate(
            ['rider_id' => $riderId],
            $this->getDefaultRiderRatingAttributes()
        );

        // Increment counters
        $riderRating->increment('total_ratings');
        $riderRating->increment('total_performance', $review->performance_rating ?? 0);
        $riderRating->increment('total_speed', $review->speed_rating ?? 0);
        $riderRating->increment('total_handling', $review->handling_rating ?? 0);
        $riderRating->increment('total_communication', $review->communication_rating ?? 0);

        // Update star distribution
        $this->updateStarDistribution($riderRating, $review->average_rating, 'increment');

        // Recalculate averages
        $this->recalculateRiderAverages($riderRating);
        $riderRating->save();

        // Update rider's cached rating
        $this->updateRiderCachedRating($riderId, $riderRating->overall_rating);
    }

    /**
     * Decrement rider ratings when a review is deleted
     */
    private function decrementRiderRatings(RiderRating $riderRating, RiderReview $review): void
    {
        $riderRating->decrement('total_ratings');
        $riderRating->decrement('total_performance', $review->performance_rating ?? 0);
        $riderRating->decrement('total_speed', $review->speed_rating ?? 0);
        $riderRating->decrement('total_handling', $review->handling_rating ?? 0);
        $riderRating->decrement('total_communication', $review->communication_rating ?? 0);

        // Update star distribution
        $this->updateStarDistribution($riderRating, $review->average_rating, 'decrement');
    }

    /**
     * Adjust rider ratings when a review is updated
     */
    private function adjustRiderRatings(int $riderId, array $oldRatings, array $newRatings): void
    {
        $riderRating = RiderRating::where('rider_id', $riderId)->first();

        if (!$riderRating) {
            return;
        }

        // Adjust totals (subtract old, add new)
        $riderRating->total_performance = $riderRating->total_performance - $oldRatings['performance'] + $newRatings['performance'];
        $riderRating->total_speed = $riderRating->total_speed - $oldRatings['speed'] + $newRatings['speed'];
        $riderRating->total_handling = $riderRating->total_handling - $oldRatings['handling'] + $newRatings['handling'];

        if (isset($oldRatings['communication']) && isset($newRatings['communication'])) {
            $riderRating->total_communication = $riderRating->total_communication - $oldRatings['communication'] + $newRatings['communication'];
        }

        // Update star distribution
        $this->updateStarDistribution($riderRating, $oldRatings['average'], 'decrement');
        $this->updateStarDistribution($riderRating, $newRatings['average'], 'increment');

        // Recalculate averages
        $this->recalculateRiderAverages($riderRating);
        $riderRating->save();

        // Update rider's cached rating
        $this->updateRiderCachedRating($riderId, $riderRating->overall_rating);
    }

    /**
     * Recalculate all averages for a rider rating record
     */
    private function recalculateRiderAverages(RiderRating $riderRating): void
    {
        $totalRatings = $riderRating->total_ratings;

        if ($totalRatings === 0) {
            $riderRating->avg_performance = 0;
            $riderRating->avg_speed = 0;
            $riderRating->avg_handling = 0;
            $riderRating->avg_communication = 0;
            $riderRating->overall_rating = 0;
            return;
        }

        $riderRating->avg_performance = round($riderRating->total_performance / $totalRatings, 2);
        $riderRating->avg_speed = round($riderRating->total_speed / $totalRatings, 2);
        $riderRating->avg_handling = round($riderRating->total_handling / $totalRatings, 2);
        $riderRating->avg_communication = round($riderRating->total_communication / $totalRatings, 2);

        // Calculate overall rating (average of all averages)
        $overall = (
            $riderRating->avg_performance +
            $riderRating->avg_speed +
            $riderRating->avg_handling +
            $riderRating->avg_communication
        ) / 4;

        $riderRating->overall_rating = round($overall, 2);
    }

    /**
     * Update star distribution for a rider rating
     */
    private function updateStarDistribution(RiderRating $riderRating, float $rating, string $operation): void
    {
        $starRating = (int) round($rating);
        $starRating = max(1, min(5, $starRating));

        $field = match ($starRating) {
            5 => 'five_star_count',
            4 => 'four_star_count',
            3 => 'three_star_count',
            2 => 'two_star_count',
            1 => 'one_star_count',
            default => null,
        };

        if ($field) {
            if ($operation === 'increment') {
                $riderRating->increment($field);
            } else {
                $riderRating->decrement($field);
            }
        }
    }

    /**
     * Update rider's cached rating field
     */
    private function updateRiderCachedRating(int $riderId, float $rating): void
    {
        $rider = \App\Models\Rider::find($riderId);

        if ($rider) {
            $rider->update(['rating' => $rating]);
        }
    }

    /**
     * Get default rider rating attributes
     */
    private function getDefaultRiderRatingAttributes(): array
    {
        return [
            'total_ratings' => 0,
            'total_performance' => 0,
            'total_speed' => 0,
            'total_handling' => 0,
            'total_communication' => 0,
            'avg_performance' => 0,
            'avg_speed' => 0,
            'avg_handling' => 0,
            'avg_communication' => 0,
            'overall_rating' => 0,
            'five_star_count' => 0,
            'four_star_count' => 0,
            'three_star_count' => 0,
            'two_star_count' => 0,
            'one_star_count' => 0,
        ];
    }
}
