<?php

namespace App\Http\Controllers\Api\V1\Rider\Reviews;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RiderReviewController extends Controller
{
  /**
   * Get Rider Reviews with Cursor Pagination
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function getReviews(Request $request)
  {
    try {
      $rider = $request->user();

      if (!$rider || !$rider->isRider()) {
        return response()->json([
          'success' => false,
          'message' => 'Unauthorized - Rider access only'
        ], 403);
      }

      $request->validate([
        'page_size' => 'required|integer|min:1|max:50',
        'cursor' => 'nullable|string',
        'rating' => 'nullable|integer|min:1|max:5'
      ]);

      $cacheKey = $this->generateReviewCacheKey($rider->id, $request);
      $result = Cache::remember($cacheKey, 30, function () use ($rider, $request) {
        return $this->fetchReviews($rider, $request);
      });

      return response()->json([
        'success' => true,
        'data' => $result
      ], 200);
    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (\Exception $e) {
      Log::error('Reviews fetch error: ' . $e->getMessage(), [
        'rider_id' => $request->user()?->id
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unable to retrieve rider reviews'
      ], 500);
    }
  }

  /**
   * Fetch reviews from database with cursor pagination
   *
   * @param Rider $rider
   * @param Request $request
   * @return array
   */
  private function fetchReviews($rider, Request $request): array
  {
    $pageSize = (int) $request->input('page_size', 10);
    $cursor = $request->input('cursor');
    $ratingFilter = $request->input('rating');

    if (!Schema::hasTable('rider_reviews')) {
      return $this->getEmptyReviewResponse();
    }

    $query = DB::table('rider_reviews')
      ->where('rider_id', $rider->id);

    if ($ratingFilter) {
      $query->where('customer_score', $ratingFilter);
    }

    if ($cursor) {
      $this->applyReviewCursor($query, $cursor);
    }

    $query->orderBy('date_created', 'desc')
      ->orderBy('id', 'desc');

    $reviews = $query->limit($pageSize + 1)->get();
    $hasMore = $reviews->count() > $pageSize;

    if ($hasMore) {
      $reviews = $reviews->slice(0, $pageSize);
    }

    $reviewIds = $reviews->pluck('order_id')->toArray();
    $customerInfo = $this->getCustomerInfoForReviews($reviewIds);
    $ratingStats = $this->getRatingStatistics($rider->id);

    $formattedReviews = $reviews->map(function ($review) use ($customerInfo) {
      $customer = $customerInfo[$review->order_id] ?? null;

      return [
        'customer_name' => $customer ? $customer['name'] : 'Customer',
        'customer_profile_picture' => $customer ? $customer['profile_picture'] : null,
        'rating' => $review->customer_score,
        'review' => $review->review_content,
        'review_date' => $review->date_created ?? $review->created_at,
        'order_id' => $review->order_id,
      ];
    })->values()->toArray();

    $nextCursor = null;
    if ($hasMore && $reviews->isNotEmpty()) {
      $lastReview = $reviews->last();
      $nextCursor = $this->generateReviewCursor($lastReview);
    }

    return [
      'average_rating' => $ratingStats['average_rating'],
      'total_reviews' => $ratingStats['total_reviews'],
      'rating_breakdown' => $ratingStats['breakdown'],
      'reviews' => $formattedReviews,
      'pagination' => [
        'has_more' => $hasMore,
        'next_cursor' => $nextCursor,
        'page_size' => $pageSize
      ]
    ];
  }

  /**
   * Get customer information for reviews
   *
   * @param array $orderIds
   * @return array
   */
  private function getCustomerInfoForReviews(array $orderIds): array
  {
    if (empty($orderIds)) {
      return [];
    }

    $orders = DB::table('orders')
      ->whereIn('id', $orderIds)
      ->whereNotNull('customer_id')
      ->get(['id', 'customer_id']);

    if ($orders->isEmpty()) {
      return [];
    }

    $customerIds = $orders->pluck('customer_id')->unique()->toArray();

    $customers = DB::table('users')
      ->whereIn('id', $customerIds)
      ->get(['id', 'firstname', 'lastname', 'profile_picture', 'image_path']);

    $customerMap = [];
    foreach ($customers as $customer) {
      $customerMap[$customer->id] = [
        'name' => trim($customer->firstname . ' ' . $customer->lastname),
        'profile_picture' => $this->getProfilePictureUrl($customer)
      ];
    }

    $result = [];
    foreach ($orders as $order) {
      if (isset($customerMap[$order->customer_id])) {
        $result[$order->id] = $customerMap[$order->customer_id];
      }
    }

    return $result;
  }

  /**
   * Get profile picture URL
   *
   * @param object $customer
   * @return string|null
   */
  private function getProfilePictureUrl($customer): ?string
  {
    $imagePath = $customer->profile_picture ?? $customer->image_path ?? null;

    if ($imagePath) {
      if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
        return $imagePath;
      }
      return asset('storage/' . $imagePath);
    }

    return asset('images/default-avatar.png');
  }

  /**
   * Get rating statistics for rider
   *
   * @param int $riderId
   * @return array
   */
  private function getRatingStatistics(int $riderId): array
  {
    if (!Schema::hasTable('rider_reviews')) {
      return [
        'average_rating' => 0,
        'total_reviews' => 0,
        'breakdown' => [
          '5_star' => 0,
          '4_star' => 0,
          '3_star' => 0,
          '2_star' => 0,
          '1_star' => 0
        ]
      ];
    }

    $stats = DB::table('rider_reviews')
      ->where('rider_id', $riderId)
      ->select(
        DB::raw('COUNT(*) as total_reviews'),
        DB::raw('AVG(customer_score) as average_rating'),
        DB::raw('SUM(CASE WHEN customer_score = 5 THEN 1 ELSE 0 END) as five_star'),
        DB::raw('SUM(CASE WHEN customer_score = 4 THEN 1 ELSE 0 END) as four_star'),
        DB::raw('SUM(CASE WHEN customer_score = 3 THEN 1 ELSE 0 END) as three_star'),
        DB::raw('SUM(CASE WHEN customer_score = 2 THEN 1 ELSE 0 END) as two_star'),
        DB::raw('SUM(CASE WHEN customer_score = 1 THEN 1 ELSE 0 END) as one_star')
      )
      ->first();

    return [
      'average_rating' => round($stats->average_rating ?? 0, 1),
      'total_reviews' => (int) ($stats->total_reviews ?? 0),
      'breakdown' => [
        '5_star' => (int) ($stats->five_star ?? 0),
        '4_star' => (int) ($stats->four_star ?? 0),
        '3_star' => (int) ($stats->three_star ?? 0),
        '2_star' => (int) ($stats->two_star ?? 0),
        '1_star' => (int) ($stats->one_star ?? 0)
      ]
    ];
  }

  /**
   * Apply cursor condition to review query
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param string $cursor
   * @return void
   */
  private function applyReviewCursor($query, string $cursor): void
  {
    $cursorData = $this->decodeReviewCursor($cursor);

    if (!$cursorData || !isset($cursorData['date_created']) || !isset($cursorData['id'])) {
      return;
    }

    $query->where(function ($q) use ($cursorData) {
      $q->where('date_created', '<', $cursorData['date_created'])
        ->orWhere(function ($subQuery) use ($cursorData) {
          $subQuery->where('date_created', '=', $cursorData['date_created'])
            ->where('id', '<', $cursorData['id']);
        });
    });
  }

  /**
   * Generate cursor for next page
   *
   * @param object $review
   * @return string
   */
  private function generateReviewCursor($review): string
  {
    $dateCreated = $review->date_created ?? $review->created_at;

    if ($dateCreated instanceof \DateTime) {
      $dateCreated = $dateCreated->format('Y-m-d H:i:s');
    }

    return base64_encode($dateCreated . '|' . $review->id);
  }

  /**
   * Decode review cursor from request
   *
   * @param string $cursor
   * @return array|null
   */
  private function decodeReviewCursor(string $cursor): ?array
  {
    try {
      $decoded = base64_decode($cursor);
      $parts = explode('|', $decoded);

      if (count($parts) !== 2) {
        return null;
      }

      return [
        'date_created' => $parts[0],
        'id' => (int) $parts[1]
      ];
    } catch (\Exception $e) {
      return null;
    }
  }

  /**
   * Get empty review response
   *
   * @return array
   */
  private function getEmptyReviewResponse(): array
  {
    return [
      'average_rating' => 0,
      'total_reviews' => 0,
      'rating_breakdown' => [
        '5_star' => 0,
        '4_star' => 0,
        '3_star' => 0,
        '2_star' => 0,
        '1_star' => 0
      ],
      'reviews' => [],
      'pagination' => [
        'has_more' => false,
        'next_cursor' => null
      ]
    ];
  }

  /**
   * Generate cache key for reviews
   *
   * @param int $riderId
   * @param Request $request
   * @return string
   */
  private function generateReviewCacheKey(int $riderId, Request $request): string
  {
    $parts = [
      'rider_reviews',
      $riderId,
      $request->input('page_size', 10),
      $request->input('rating', 'all'),
      md5($request->input('cursor', 'first'))
    ];

    return implode(':', $parts);
  }
}
