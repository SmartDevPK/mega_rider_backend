<?php
// app/Http/Controllers/ReferralController.php

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReferralController extends Controller
{
    protected $referralService;

    public function __construct(ReferralService $referralService)
    {
        $this->referralService = $referralService;
    }

    /**
     * GET /api/referrals/leaderboard
     */
    public function leaderboard(Request $request)
    {
        $user = $request->user();
        $limit = min($request->input('limit', 10), 50);
        $currentMonth = now()->format('Y-m');
        $cacheKey = "referral:leaderboard:{$currentMonth}:{$limit}";

        // Check cache
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json([
                'success' => true,
                'message' => 'Monthly leaderboard fetched successfully',
                'data' => $cached
            ]);
        }

        // Fetch leaderboard from database
        // Assumes a table `customer_referral_points` with columns: customer_id, month, monthly_points
        $leaderboard = DB::table('customer_referral_points as crp')
            ->join('users', 'crp.customer_id', '=', 'users.id')
            ->select(
                'users.id',
                DB::raw("CONCAT(users.firstname, ' ', users.lastname) as fullname"),
                'users.profile_picture as profile_image_url',
                'crp.monthly_points as total_points'
            )
            ->where('crp.month', $currentMonth)
            ->orderBy('crp.monthly_points', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item, $index) {
                return [
                    'fullname' => $item->fullname,
                    'position' => $index + 1,
                    'profile_image_url' => $item->profile_image_url,
                    'total_points' => (int) $item->total_points,
                ];
            });

        // Get current user's points and rank
        $userPoints = DB::table('customer_referral_points')
            ->where('customer_id', $user->id)
            ->where('month', $currentMonth)
            ->value('monthly_points') ?? 0;

        $userRank = DB::table('customer_referral_points')
            ->where('month', $currentMonth)
            ->where('monthly_points', '>', $userPoints)
            ->count() + 1;

        $currentUserData = null;
        $isInTopList = $leaderboard->contains('fullname', $user->firstname . ' ' . $user->lastname);
        
        if (!$isInTopList) {
            $currentUserData = [
                'fullname' => $user->firstname . ' ' . $user->lastname,
                'position' => $userRank,
                'profile_image_url' => $user->profile_picture,
                'total_points' => $userPoints,
                'is_current_user' => true,
            ];
        }

        $responseData = [
            'month' => $currentMonth,
            'leaderboard' => $leaderboard,
            'current_user' => $currentUserData,
        ];

        // Cache for 60 seconds
        Cache::put($cacheKey, $responseData, 60);

        return response()->json([
            'success' => true,
            'message' => 'Monthly leaderboard fetched successfully',
            'data' => $responseData
        ]);
    }
}