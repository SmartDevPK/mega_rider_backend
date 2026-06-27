<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;

class PromotionController extends Controller
{
    protected $promotionService;

    public function __construct(PromotionService $promotionService)
    {
        $this->promotionService = $promotionService;
    }

    public function live(): JsonResponse
    {
        try {
            $campaigns = $this->promotionService->getLivePromotions();

            if ($campaigns->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'code' => 'NO_ACTIVE_CAMPAIGNS'
                ], 404);
            }

            // Transform response
            $data = $campaigns->map(function ($campaign) {
                return [
                    'campaign_title' => $campaign->title,
                    'campaign_body'  => $campaign->body,
                    'campaign_code'  => $campaign->promo_code,
                    'ends_by'        => $campaign->ends_by->toIso8601String(),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Live promotional campaigns fetched successfully',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            \Log::error('Promotion error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'code' => 'SERVER_ERROR'
            ], 500);
        }
    }
}
