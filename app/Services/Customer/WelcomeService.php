<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\Rider;
use App\Models\RideRating;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class WelcomeService
{
    /**
     * Generate complete welcome message for new customer
     */
    public function generateWelcomeMessage(Customer $customer): array
    {
        return [
            'title' => $this->getWelcomeTitle($customer),
            'message' => $this->getWelcomeMessage($customer),
            'bonus' => $this->getWelcomeBonus($customer),
            'stats' => $this->getWelcomeStats(),
            'next_steps' => $this->getNextSteps($customer),
            'features' => $this->getKeyFeatures(),
            'tips' => $this->getSafetyTips(),
            'referral_info' => $this->getReferralInfo($customer),
            'social_proof' => $this->getSocialProof(),
        ];
    }

    /**
     * Personalized welcome title
     */
    private function getWelcomeTitle(Customer $customer): string
    {
        $titles = [
            "🚀 Welcome to MegaRide, {$customer->first_name}!",
            "🎉 You're in! Welcome {$customer->first_name}!",
            "🌟 MegaRide Family just got brighter! Welcome {$customer->first_name}!",
            "💚 We've been waiting for you! Welcome {$customer->first_name}!",
            "🏆 A new rider joins MegaRide! Welcome {$customer->first_name}!",
            "⚡ Fasten your seatbelt, {$customer->first_name}! Welcome to MegaRide!",
            "🎊 MegaRide welcomes {$customer->first_name}! Let's ride!",
        ];

        return $titles[array_rand($titles)];
    }

    /**
     * Main welcome message
     */
    private function getWelcomeMessage(Customer $customer): string
    {
        $hour = Carbon::now()->hour;

        if ($hour < 12) {
            $greeting = "Good morning";
        } elseif ($hour < 17) {
            $greeting = "Good afternoon";
        } else {
            $greeting = "Good evening";
        }

        return "{$greeting}, {$customer->first_name}! Thank you for choosing MegaRide. " .
            "You're now part of Nigeria's fastest-growing ride-hailing platform. " .
            "We're committed to giving you safe, affordable, and reliable rides anytime, anywhere.";
    }

    /**
     * Welcome bonus details
     */
    private function getWelcomeBonus(Customer $customer): array
    {
        // Check if welcome bonus already given
        $bonusGiven = Cache::get("welcome_bonus:{$customer->id}");

        if (!$bonusGiven && $customer->total_rides === 0) {
            try {
                // Give welcome bonus
                $customer->wallet_balance += 1000;
                $customer->points_balance += 500;
                $customer->save();

                Cache::put("welcome_bonus:{$customer->id}", true, now()->addDays(30));

                return [
                    'received' => true,
                    'wallet_bonus' => 1000,
                    'points_bonus' => 500,
                    'message' => "₦1000 added to your wallet + 500 loyalty points! 🎉",
                    'first_ride_discount' => "₦500 off your first ride",
                ];
            } catch (\Exception $e) {
                return [
                    'received' => false,
                    'message' => "Welcome bonus will be added on your first ride!",
                ];
            }
        }

        return [
            'received' => false,
            'message' => "You've already received your welcome bonus. Check your wallet!",
        ];
    }

    /**
     * Platform statistics
     */
    private function getWelcomeStats(): array
    {
        return [
            'total_rides' => $this->getTotalRides(),
            'active_riders' => $this->getActiveRiders(),
            'happy_customers' => $this->getTotalCustomers(),
            'average_rating' => $this->getAverageRating(),
            'response_time' => '< 3 minutes',
            'satisfaction_rate' => '98%',
        ];
    }

    /**
     * Next steps for new user
     */
    private function getNextSteps(Customer $customer): array
    {
        $steps = [
            [
                'step' => 1,
                'title' => 'Verify Your Email',
                'description' => 'Unlock all features and get extra 100 points',
                'action' => 'verify_email',
                'completed' => !is_null($customer->email_verified_at),
                'link' => '/verify-email',
            ],
            [
                'step' => 2,
                'title' => 'Complete Your Profile',
                'description' => 'Add profile picture and save favorite addresses',
                'action' => 'complete_profile',
                'completed' => !is_null($customer->profile_picture),
                'link' => '/profile',
            ],
            [
                'step' => 3,
                'title' => 'Add Payment Method',
                'description' => 'Add card or bank account for faster checkout',
                'action' => 'add_payment',
                'completed' => false,
                'link' => '/payment-methods',
            ],
            [
                'step' => 4,
                'title' => 'Book Your First Ride',
                'description' => 'Get ₦500 off your first ride!',
                'action' => 'book_ride',
                'completed' => $customer->total_rides > 0,
                'link' => '/book-ride',
            ],
            [
                'step' => 5,
                'title' => 'Invite Friends',
                'description' => 'Earn ₦5000 for each friend who joins',
                'action' => 'invite_friends',
                'completed' => false,
                'link' => '/referral',
            ],
        ];

        return $steps;
    }

    /**
     * Key features of MegaRide
     */
    private function getKeyFeatures(): array
    {
        return [
            [
                'icon' => '🚗',
                'title' => 'Multiple Vehicle Options',
                'description' => 'Choose from Cars, Bikes, or Mini-buses',
            ],
            [
                'icon' => '💳',
                'title' => 'Flexible Payments',
                'description' => 'Pay with Wallet, Card, or Cash',
            ],
            [
                'icon' => '🛡️',
                'title' => 'Safety First',
                'description' => 'SOS button + Share trip + Women riders',
            ],
            [
                'icon' => '⭐',
                'title' => 'Loyalty Program',
                'description' => 'Earn points on every ride. Get up to 15% off!',
            ],
            [
                'icon' => '📅',
                'title' => 'Schedule Rides',
                'description' => 'Book rides up to 7 days in advance',
            ],
            [
                'icon' => '👥',
                'title' => 'Split Fare',
                'description' => 'Share ride cost with friends',
            ],
        ];
    }

    /**
     * Safety tips for new users
     */
    private function getSafetyTips(): array
    {
        return [
            [
                'title' => 'Share Your Trip',
                'description' => 'Share your ride details with up to 5 trusted contacts',
                'action' => 'Share trip',
            ],
            [
                'title' => 'Verify Rider',
                'description' => 'Check rider photo, name, and plate number before entering',
                'action' => 'Check details',
            ],
            [
                'title' => 'Emergency SOS',
                'description' => 'Tap SOS button in case of emergency. Police alerted instantly',
                'action' => 'Learn more',
            ],
            [
                'title' => 'Rate Your Ride',
                'description' => 'Rate riders to help maintain quality service',
                'action' => 'Rate rides',
            ],
        ];
    }

    /**
     * Referral information
     */
    private function getReferralInfo(Customer $customer): array
    {
        return [
            'code' => $customer->referral_code,
            'share_link' => "https://megaride.com/join?ref={$customer->referral_code}",
            'rewards' => [
                'you_get' => '₦5000 per friend',
                'friend_gets' => '₦2000 first ride discount',
                'after_10_friends' => '₦50000 bonus + Platinum tier',
            ],
            'how_it_works' => [
                'Share your code',
                'Friend signs up',
                'Friend takes first ride',
                'You both get rewards!',
            ],
        ];
    }

    /**
     * Social proof messages
     */
    private function getSocialProof(): array
    {
        return [
            'trustpilot_rating' => '4.8/5',
            'google_rating' => '4.7/5',
            'total_downloads' => '500,000+',
            'daily_rides' => '10,000+',
            'testimonials' => [
                [
                    'name' => 'Adebayo O.',
                    'rating' => 5,
                    'comment' => 'Best ride app in Nigeria! Always on time and affordable.',
                    'ride_count' => 150,
                ],
                [
                    'name' => 'Ifeanyi C.',
                    'rating' => 5,
                    'comment' => 'The safety features give me peace of mind. Love it!',
                    'ride_count' => 89,
                ],
                [
                    'name' => 'Ngozi E.',
                    'rating' => 5,
                    'comment' => 'Finally a ride app that cares about customers.',
                    'ride_count' => 234,
                ],
            ],
        ];
    }

    // =========================================================================
    // HELPER METHODS TO GET REAL DATA
    // =========================================================================

    /**
     * Get total rides count
     */
    private function getTotalRides(): int
    {
        return Cache::remember('total_rides_stats', 3600, function () {
            try {
                // Try to get from trips table if it exists
                if (Schema::hasTable('trips')) {
                    return DB::table('trips')->count();
                }
                // Fallback to Rider model
                return Rider::count();
            } catch (\Exception $e) {
                return 12500; // Placeholder for demo
            }
        });
    }

    /**
     * Get active riders (online drivers) count
     * REMOVED: getActiveDrivers() - Using getActiveRiders() instead
     */
    private function getActiveRiders(): int
    {
        return Cache::remember('active_riders_stats', 600, function () {
            try {
                // Check if riders table exists and has is_online column
                if (Schema::hasTable('riders')) {
                    return Rider::where('is_online', true)
                        ->where('is_active', true)
                        ->where('verification_status', 'approved')
                        ->count();
                }
                return 0;
            } catch (\Exception $e) {
                return 0; // Return 0 if table doesn't exist yet
            }
        });
    }

    /**
     * Get total customers count
     */
    private function getTotalCustomers(): int
    {
        return Cache::remember('total_customers_stats', 3600, function () {
            try {
                return Customer::count();
            } catch (\Exception $e) {
                return 25000; // Placeholder for demo
            }
        });
    }

    /**
     * Get average rating
     */
    private function getAverageRating(): float
    {
        return Cache::remember('average_rating_stats', 3600, function () {
            try {
                if (Schema::hasTable('ride_ratings')) {
                    return round(RideRating::avg('customer_rating') ?? 4.8, 1);
                }
                return 4.8; // Default rating
            } catch (\Exception $e) {
                return 4.8;
            }
        });
    }
}
