<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Services\ReasonService;
use Illuminate\Http\JsonResponse;

class ReasonController extends Controller
{
    public function __construct(protected ReasonService $service) {}

    public function reportReasons(): JsonResponse
    {
        try {
            $reasons = $this->service->getReportReasons();

            return response()->json([
                'success' => true,
                'message' => 'Report reasons fetched successfully',
                'data'    => $reasons
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'code'    => 'SERVER_ERROR'
            ], 500);
        }
    }

    public function cancellationReasons(): JsonResponse
    {
        try {
            $reasons = $this->service->getCancellationReasons();

            return response()->json([
                'success' => true,
                'message' => 'Cancellation reasons fetched successfully',
                'data'    => $reasons
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'code'    => 'SERVER_ERROR'
            ], 500);
        }
    }
}
