<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    protected ReviewService $reviewService;

    public function __construct(ReviewService $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    /**
     * Store a new rider review
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        try {
            $result = $this->reviewService->submitReview(
                $request->validated(),
                $request->user()->id // cleaner than auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully',
                'data' => $result
            ], 201);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $e->errors()
            ], 422);

        } catch (\Throwable $e) {

            // Optional: log error for debugging
            \Log::error('Review submission failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.'
            ], 500);
        }
    }
}
