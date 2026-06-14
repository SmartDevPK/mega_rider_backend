<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ZONES TABLE
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('name');
        });


        // 2. CUSTOMERS TABLE
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 20)->unique();
            $table->string('registration_ip', 45)->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('profile_picture')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('referral_code')->nullable()->unique();
            $table->string('referred_by')->nullable();
            $table->boolean('referral_rewarded')->default(false);
            $table->decimal('wallet_balance', 12, 2)->default(0);
            $table->integer('points_balance')->default(0);
            $table->integer('total_rides')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->json('notification_preferences')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('locale', 10)->default('en');
            $table->string('email_verification_code', 8)->nullable();
            $table->string('password_reset_code', 6)->nullable();
            $table->timestamp('password_reset_expires_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->text('deactivation_reason')->nullable();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->timestamp('email_verification_sent_at')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->integer('login_count')->default(0);
            $table->string('fcm_token')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['email', 'is_active'], 'idx_customers_email_active');
            $table->index(['phone_number', 'is_active'], 'idx_customers_phone_active');
            $table->index('referral_code', 'idx_customers_referral');
            $table->index('created_at', 'idx_customers_created');
            $table->index(['first_name', 'last_name'], 'idx_customers_name');
            $table->index('zone_id', 'idx_customers_zone');
            $table->index('wallet_balance', 'idx_customers_balance');
            $table->index('fcm_token', 'idx_customers_fcm');
        });

        // 3. RIDERS TABLE
        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 20)->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->string('first_name');
            $table->string('last_name');
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('address')->nullable();
            $table->string('nin', 11)->unique()->nullable();
            $table->string('profile_picture')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('rating', 2, 1)->default(0);
            $table->integer('total_trips')->default(0);
            $table->integer('total_deliveries')->default(0);
            $table->integer('completed_trips')->default(0);
            $table->integer('cancelled_trips')->default(0);
            $table->decimal('acceptance_rate', 5, 2)->default(100.00);
            $table->boolean('is_online')->default(false);
            $table->boolean('is_available')->default(false);
            $table->boolean('is_busy')->default(false);
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->decimal('current_latitude', 10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->timestamp('last_status_update')->nullable();
            $table->enum('vehicle_type', ['car', 'bike', 'bicycle', 'tricycle', 'auto'])->default('car');
            $table->string('vehicle_color', 50)->nullable();
            $table->string('vehicle_plate_number')->unique();
            $table->string('vehicle_model')->nullable();
            $table->integer('seating_capacity')->default(4);
            $table->string('driver_license_number')->unique();
            $table->string('driver_license_path')->nullable();
            $table->string('proof_of_address_path')->nullable();
            $table->timestamp('license_verified_at')->nullable();
            $table->boolean('background_check_passed')->default(false);
            $table->boolean('phone_verified')->default(false);
            $table->decimal('total_earned', 12, 2)->default(0);
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->decimal('pending_payout', 12, 2)->default(0);
            $table->decimal('total_withdrawn', 12, 2)->default(0);
            $table->string('guarantor_name')->nullable();
            $table->string('guarantor_phone', 20)->nullable();
            $table->string('guarantor_relationship', 50)->nullable();
            $table->string('guarantor_address')->nullable();
            $table->string('guarantor_occupation')->nullable();
            $table->string('nok_name')->nullable();
            $table->string('nok_phone', 20)->nullable();
            $table->string('nok_relationship', 50)->nullable();
            $table->string('nok_address')->nullable();
            $table->string('previous_place_of_work')->nullable();
            $table->integer('years_of_work')->default(0);
            $table->enum('verification_status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->boolean('two_factor_auth')->default(false);
            $table->timestamp('password_updated_at')->nullable();
            $table->timestamp('password_set_at')->nullable();
            $table->string('password_reset_token')->nullable();
            $table->timestamp('password_reset_token_expires_at')->nullable();
            $table->integer('password_reset_attempts')->default(0);
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->integer('otp_attempts')->default(0);
            $table->timestamp('otp_last_attempt_at')->nullable();
            $table->string('email_verification_code', 10)->nullable();
            $table->timestamp('email_verification_sent_at')->nullable();
            $table->boolean('email_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('fcm_token')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->integer('login_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['is_available', 'rating', 'zone_id'], 'idx_riders_matching');
            $table->index(['zone_id', 'is_available', 'rating'], 'idx_riders_zone_available');
            $table->index(['current_latitude', 'current_longitude'], 'idx_riders_location');
            $table->index('location_updated_at', 'idx_riders_location_time');
            $table->index(['email', 'is_active'], 'idx_riders_email_active');
            $table->index(['phone_number', 'is_active'], 'idx_riders_phone_active');
            $table->index(['verification_status', 'created_at'], 'idx_riders_status_created');
            $table->index('verification_status', 'idx_riders_verification');
            $table->index('vehicle_plate_number', 'idx_riders_plate');
            $table->index('driver_license_number', 'idx_riders_license');
            $table->index('nin', 'idx_riders_nin');
            $table->index(['first_name', 'last_name'], 'idx_riders_name');
            $table->index('rating', 'idx_riders_rating');
            $table->index('is_online', 'idx_riders_online');
            $table->index('fcm_token', 'idx_riders_fcm');
            $table->index('zone_id', 'idx_riders_zone');
            $table->index('approved_by', 'idx_riders_approved_by');
            $table->index('created_at', 'idx_riders_created');
            $table->index('last_login_at', 'idx_riders_last_login');
            $table->index('otp_code', 'idx_riders_otp');
            $table->index('otp_expires_at', 'idx_riders_otp_expires');
            $table->index('password_reset_token', 'idx_riders_reset_token');
            $table->index('email_verification_code', 'idx_riders_email_verification');
        });

        // 4. ADMINS TABLE
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->string('profile_picture')->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->enum('role', ['super_admin', 'admin', 'support', 'finance', 'operations', 'marketing'])->default('admin');
            $table->boolean('is_super_admin')->default(false);
            $table->json('permissions')->nullable();
            $table->json('dashboard_preferences')->nullable();
            $table->string('language')->default('en');
            $table->string('timezone')->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->string('deletion_reason')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('password_updated_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->integer('login_count')->default(0);
            $table->timestamp('last_action_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'is_active'], 'idx_admins_email_active');
            $table->index('role', 'idx_admins_role');
            $table->index('is_super_admin', 'idx_admins_super');
            $table->index('last_login_at', 'idx_admins_last_login');
            $table->index(['is_active', 'role'], 'idx_admins_active_role');
        });

        // 5. CUSTOMER ADDRESSES
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label');
            $table->text('address');
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'is_default'], 'idx_customer_addresses_default');
            $table->index(['latitude', 'longitude'], 'idx_customer_addresses_coords');
            $table->index('city', 'idx_customer_addresses_city');
        });

        // 6. RIDER VEHICLES
        Schema::create('rider_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->string('model');
            $table->string('color');
            $table->string('license_plate')->unique();
            $table->enum('vehicle_type', ['car', 'bike', 'auto', 'truck'])->default('car');
            $table->integer('seating_capacity')->default(4);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_approved')->default(false);
            $table->json('photos')->nullable();
            $table->timestamps();

            $table->index('license_plate', 'idx_rider_vehicles_plate');
            $table->index(['rider_id', 'is_active'], 'idx_rider_vehicles_active');
            $table->index('vehicle_type', 'idx_rider_vehicles_type');
        });

        // 7. TRIPS TABLE (Moved BEFORE rider_earnings)
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('rider_id')->constrained('riders');
            $table->enum('status', ['pending', 'accepted', 'arrived', 'ongoing', 'completed', 'cancelled'])->default('pending');
            $table->decimal('pickup_latitude', 10, 7);
            $table->decimal('pickup_longitude', 10, 7);
            $table->text('pickup_address')->nullable();
            $table->decimal('dropoff_latitude', 10, 7);
            $table->decimal('dropoff_longitude', 10, 7);
            $table->text('dropoff_address')->nullable();
            $table->decimal('estimated_fare', 10, 2);
            $table->decimal('final_fare', 10, 2)->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->decimal('customer_rating', 2, 1)->nullable();
            $table->decimal('rider_rating', 2, 1)->nullable();
            $table->text('customer_feedback')->nullable();
            $table->text('rider_feedback')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at'], 'idx_trips_customer');
            $table->index(['rider_id', 'created_at'], 'idx_trips_rider');
            $table->index('status', 'idx_trips_status');
            $table->index('created_at', 'idx_trips_created');
            $table->index(['status', 'created_at'], 'idx_trips_status_created');
        });

        // 8. RIDER EARNINGS (Now after trips)
        Schema::create('rider_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('commission', 10, 2)->default(0);
            $table->decimal('net_earning', 10, 2);
            $table->enum('type', ['trip', 'bonus', 'refund', 'adjustment']);
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'status', 'created_at'], 'idx_rider_earnings_status');
            $table->index('created_at', 'idx_rider_earnings_date');
            $table->index('trip_id', 'idx_rider_earnings_trip');
        });

        // 9. PAYMENTS TABLE
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('rider_id')->constrained('riders');
            $table->decimal('amount', 10, 2);
            $table->decimal('commission', 10, 2)->default(0);
            $table->enum('method', ['wallet', 'card', 'cash', 'bank_transfer']);
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('transaction_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('trip_id', 'idx_payments_trip');
            $table->index('customer_id', 'idx_payments_customer');
            $table->index('transaction_id', 'idx_payments_transaction');
            $table->index('status', 'idx_payments_status');
        });

        // 10. ACTIVITY LOGS
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('actor');
            $table->string('action');
            $table->string('ip_address', 45);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['action', 'created_at'], 'idx_activity_action_date');
            $table->index(['actor_type', 'actor_id', 'created_at'], 'idx_activity_actor');
        });

        // 11. PASSWORD RESET TOKENS
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();

            $table->index(['token', 'created_at'], 'idx_password_reset_token');
            $table->index('created_at', 'idx_password_reset_created');
        });

        // 12. SESSIONS
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id')->nullable();
            $table->string('user_type')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();

            $table->index(['user_id', 'user_type'], 'idx_sessions_user');
            $table->index('last_activity', 'idx_sessions_activity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('rider_earnings');
        Schema::dropIfExists('trips');
        Schema::dropIfExists('rider_vehicles');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('riders');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('zones');
    }
};
