<?php

namespace App\Services\Review;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;

class DraftService
{
  const MAX_DRAFTS = 5;
  const EXPIRY_HOURS = 24;

  /**
   * Auto-save or update a draft.
   */
  public function autoSave(int $customerId, array $data): Order
  {
    // If updating existing draft
    if (isset($data['order_id'])) {
      //  Use order_id (UUID) column instead of id
      $draft = Order::where('customer_id', $customerId)
        ->where('order_id', $data['order_id'])
        ->where('status', 'draft')
        ->firstOrFail();
    } else {
      // Limit check for new drafts
      $count = Order::where('customer_id', $customerId)
        ->where('status', 'draft')
        ->count();
      if ($count >= self::MAX_DRAFTS) {
        throw new \Exception('Draft limit reached', 400);
      }
      $draft = new Order([
        'customer_id' => $customerId,
        'status' => 'draft',
        'order_id' => $this->generateDraftId(),
      ]);
    }

    // Update step and step-specific fields
    $step = $data['step'];
    $draft->step = $step;

    switch ($step) {
      case 'pickup':
        $draft->pickup_address = $data['pickup_address'] ?? $draft->pickup_address;
        break;
      case 'dropoff':
        $draft->dropoff_address = $data['dropoff_address'] ?? $draft->dropoff_address;
        break;
      case 'item':
        $draft->item_name = $data['item_name'] ?? $draft->item_name;
        $draft->package_image = $data['package_image'] ?? $draft->package_image;
        break;
      case 'review':
        // nothing extra to save
        break;
    }

    if (isset($data['meta'])) {
      $draft->meta = array_merge($draft->meta ?? [], $data['meta']);
    }

    $draft->save();

    // Invalidate cache for this user
    $this->clearCache($customerId);

    return $draft;
  }

  /**
   * Get all drafts for a user (cached).
   */
  public function getUserDrafts(int $customerId): array
  {
    $cacheKey = "drafts:user:{$customerId}";
    return Cache::remember($cacheKey, 60, function () use ($customerId) {
      return Order::where('customer_id', $customerId)
        ->where('status', 'draft')
        ->where('created_at', '>', now()->subHours(self::EXPIRY_HOURS))
        ->orderBy('updated_at', 'desc')
        ->get()
        ->map(fn($draft) => $this->formatDraftSummary($draft))
        ->toArray();
    });
  }

  /**
   * Resume a single draft – checks ownership and expiry.
   * 
   * @param int $customerId
   * @param string $orderId  // 
   */
  public function resume(int $customerId, string $orderId): Order
  {
    $draft = Order::where('customer_id', $customerId)
      ->where('order_id', $orderId)
      ->where('status', 'draft')
      ->first();

    if (!$draft) {
      throw new \Exception('Draft not found', 404);
    }

    if ($draft->isExpired()) {
      $draft->delete();
      $this->clearCache($customerId);
      throw new \Exception('Draft expired', 404);
    }

    return $draft;
  }

  /**
   * Delete a draft (e.g., after submission).
   * 
   * @param int $customerId
   * @param string $orderId  // 
   */
  public function deleteDraft(int $customerId, string $orderId): void
  {
    Order::where('customer_id', $customerId)
      ->where('order_id', $orderId)   // 
      ->where('status', 'draft')
      ->delete();
    $this->clearCache($customerId);
  }

  /**
   * Convert a draft into a real order (status = pending).
   * Returns the updated order.
   * 
   * @param int $customerId
   * @param string $orderId  // 
   */
  public function submitDraft(int $customerId, string $orderId, array $finalData = []): Order
  {
    $draft = $this->resume($customerId, $orderId);

    // Merge any final data (e.g., coordinates, sender info, etc.)
    $draft->fill($finalData);
    $draft->status = 'pending';
    $draft->step = null; // no longer a draft
    $draft->save();

    $this->clearCache($customerId);
    return $draft;
  }

  private function generateDraftId(): string
  {
    do {
      $id = 'DRF' . strtoupper(\Illuminate\Support\Str::random(6));
    } while (Order::where('order_id', $id)->exists());
    return $id;
  }

  private function formatDraftSummary(Order $draft): array
  {
    $expiresAt = $draft->created_at->addHours(self::EXPIRY_HOURS);
    return [
      'order_id'          => $draft->order_id,
      'step'              => $draft->step,
      'item_name'         => $draft->item_name,
      'package_image'     => $draft->package_image,
      'pickup_address'    => $draft->pickup_address,
      'delivery_address'  => $draft->dropoff_address,
      'expires_in'        => now()->diffInMinutes($expiresAt, false) > 0 ? now()->diffInMinutes($expiresAt) . ' minutes' : 'expired',
      'can_resume'        => !$draft->isExpired(),
    ];
  }

  private function clearCache(int $customerId): void
  {
    Cache::forget("drafts:user:{$customerId}");
  }
}
