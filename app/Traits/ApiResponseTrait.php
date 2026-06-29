<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

trait ApiResponseTrait
{
    protected function successResponse($data, string $requestId, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'request_id' => $requestId,
                'timestamp' => now()->toIso8601String(),
                'version' => '2.0.0'
            ]
        ], $status);
    }

    protected function errorResponse(
        string $code,
        string $message,
        ?array $details = null,
        int $status = Response::HTTP_BAD_REQUEST,
        ?string $requestId = null
    ): JsonResponse {
        $response = [
            'status' => 'error',
            'code' => $code,
            'message' => $message,
            'meta' => [
                'request_id' => $requestId ?? $this->generateRequestId(),
                'timestamp' => now()->toIso8601String(),
                'version' => '2.0.0'
            ]
        ];

        if ($details) {
            $response['details'] = $details;
        }

        return response()->json($response, $status);
    }

    protected function generateRequestId(): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6))
        );
    }

    protected function getResponseTime(): string
    {
        return number_format((microtime(true) - LARAVEL_START) * 1000, 2) . 'ms';
    }

    protected function formatUserResponse($user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name ?? null,
            'last_name' => $user->last_name ?? null,
            'full_name' => isset($user->first_name, $user->last_name)
                ? trim($user->first_name . ' ' . $user->last_name)
                : null,
            'email' => $user->email,
            'phone_number' => $user->phone_number ?? null,
            'is_verified' => $user->is_verified ?? false,
            'is_active' => $user->is_active ?? true,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
