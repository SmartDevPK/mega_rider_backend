<?php

namespace App\Services\Rider;

use App\DTO\Rider\RiderActivityDTO;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RiderActivityService
{
    protected int $riderId;
    protected int $pageSize;
    protected ?string $cursor;
    protected ?string $orderIdFilter;
    protected ?string $statusFilter;

    /**
     * Initialize service with request data
     */
    public function initialize(Request $request): self
    {
        $this->riderId = auth()->id();
        $this->pageSize = $request->input('page_size', 15);
        $this->cursor = $request->input('cursor');
        $this->orderIdFilter = $request->input('order_id');
        $this->statusFilter = $request->input('order_status');
        
        return $this;
    }

    /**
     * Get paginated rider activities
     */
    public function getActivities(): array
    {
        // Try to get from cache first (optional)
        $cacheKey = $this->generateCacheKey();
        
        if (config('cache.enabled', false)) {
            return Cache::remember($cacheKey, 30, function () {
                return $this->fetchActivities();
            });
        }
        
        return $this->fetchActivities();
    }

    /**
     * Fetch activities from database with cursor pagination
     */
    protected function fetchActivities(): array
    {
        try {
            // Build base query
            $query = Order::where('rider_id', $this->riderId)
                ->where('is_draft', false);
            
            // Apply filters
            $this->applyFilters($query);
            
            // Apply cursor pagination
            $this->applyCursor($query);
            
            // Order by date_modified and id (both DESC for cursor pagination)
            $query->orderBy('date_modified', 'desc')
                  ->orderBy('id', 'desc');
            
            // Fetch one extra record to determine if there's a next page
            $orders = $query->limit($this->pageSize + 1)->get();
            
            // Determine if there's a next page
            $hasNextPage = $orders->count() > $this->pageSize;
            
            // Remove the extra record if it exists
            if ($hasNextPage) {
                $orders = $orders->slice(0, $this->pageSize);
            }
            
            // Transform to DTOs
            $activities = $orders->map(function ($order) {
                return RiderActivityDTO::fromOrder($order)->toArray();
            })->values()->toArray();
            
            // Generate next cursor
            $nextCursor = null;
            if ($hasNextPage && $orders->isNotEmpty()) {
                $lastOrder = $orders->last();
                $nextCursor = $this->generateCursor($lastOrder);
            }
            
            return [
                'success' => true,
                'has_next_page' => $hasNextPage,
                'next_cursor' => $nextCursor,
                'activities' => $activities,
                'total_count' => $hasNextPage ? null : $orders->count(), // Optional metadata
            ];
            
        } catch (\Exception $e) {
            Log::error('Rider activities fetch failed', [
                'rider_id' => $this->riderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \Exception('Unable to fetch activities');
        }
    }

    /**
     * Apply optional filters to query
     */
    protected function applyFilters($query): void
    {
        // Filter by order_id (partial match)
        if ($this->orderIdFilter) {
            $query->where('order_id', 'like', '%' . $this->orderIdFilter . '%');
        }
        
        // Filter by order status
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
    }

    /**
     * Apply cursor pagination
     */
    protected function applyCursor($query): void
    {
        if (!$this->cursor) {
            return;
        }
        
        // Decode cursor (format: date_modified|id)
        $cursorData = $this->decodeCursor($this->cursor);
        
        if (!$cursorData || !isset($cursorData['date_modified']) || !isset($cursorData['id'])) {
            return;
        }
        
        // Apply cursor condition
        $query->where(function ($q) use ($cursorData) {
            $q->where('date_modified', '<', $cursorData['date_modified'])
              ->orWhere(function ($subQuery) use ($cursorData) {
                  $subQuery->where('date_modified', '=', $cursorData['date_modified'])
                           ->where('id', '<', $cursorData['id']);
              });
        });
    }

    /**
     * Generate cursor for next page
     */
    protected function generateCursor($order): string
    {
        $dateModified = $order->date_modified instanceof \DateTime 
            ? $order->date_modified->format('Y-m-d\TH:i:s\Z')
            : date('Y-m-d\TH:i:s\Z', strtotime($order->date_modified));
            
        return base64_encode($dateModified . '|' . $order->id);
    }

    /**
     * Decode cursor from request
     */
    protected function decodeCursor(string $cursor): ?array
    {
        try {
            $decoded = base64_decode($cursor);
            $parts = explode('|', $decoded);
            
            if (count($parts) !== 2) {
                return null;
            }
            
            return [
                'date_modified' => $parts[0],
                'id' => (int) $parts[1]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate cache key for activities
     */
    protected function generateCacheKey(): string
    {
        $parts = [
            'rider_activities',
            $this->riderId,
            md5($this->cursor ?? 'first'),
            $this->pageSize,
            $this->orderIdFilter ?? 'none',
            $this->statusFilter ?? 'none'
        ];
        
        return implode(':', $parts);
    }
}