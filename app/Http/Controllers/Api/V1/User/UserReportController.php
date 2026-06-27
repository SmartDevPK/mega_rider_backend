<?php

namespace App\Http\Controllers\Api\V1\User;


use App\Http\Requests\StoreUserReportRequest;
use App\Services\UserReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserReportController extends Controller
{
    public function __construct(protected UserReportService $service) {}

    public function store(StoreUserReportRequest $request): JsonResponse
    {
        try {
            $report = $this->service->createReport(
                $request->validated(),
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'User reported successfully',
                'data'    => $report
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }
}
