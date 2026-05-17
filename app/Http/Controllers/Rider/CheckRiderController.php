<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Services\Rider\RiderCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckRiderController extends Controller
{
    protected RiderCheckService $riderCheckService;

    public function __construct(RiderCheckService $riderCheckService)
    {
        $this->riderCheckService = $riderCheckService;
    }

    /**
     * Check Rider Email Status
     *
     * POST /api/rider/check-email
     */
    public function checkEmail(Request $request): JsonResponse
    {
        // 1. VALIDATE INPUT
        $validated = $request->validate([
            'email' => ['required', 'email']
        ]);

        // 2. CALL SERVICE
        $response = $this->riderCheckService->check($validated['email']);

        // 3. MAP HTTP STATUS CODE
        $statusCode = $this->mapStatusCode($response);

        // 4. RETURN RESPONSE
        return response()->json($response, $statusCode);
    }

    /**
     * Map business response → HTTP status code
     */
    private function mapStatusCode(array $response): int
    {
        // Banned → Forbidden
        if (($response['code'] ?? null) === 'ACCOUNT_BANNED') {
            return 403;
        }

        // Not registered → Unauthorized
        if (($response['code'] ?? null) === 'NOT_REGISTERED') {
            return 401;
        }

        // All other incomplete steps → 200
        return 200;
    }
}