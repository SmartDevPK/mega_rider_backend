<?php
namespace App\Services;

use App\Models\Promotion;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class PromotionService
{
    public function getLivePromotions()
    {
        $cacheKey = 'promotions:live';

        return Cache::remember($cacheKey, 60, function () {

            $now = Carbon::now('Africa/Lagos');

            $campaigns = Promotion::select('title', 'body', 'promo_code', 'ends_by')
                ->where('ends_by', '>', $now)
                ->orderBy('ends_by', 'asc')
                ->get();

            return $campaigns;
        });
    }
}
