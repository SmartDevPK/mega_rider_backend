<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ========================================
        // Zones Table (must exist before users)
        // ========================================
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->geometry('boundary')->nullable(); // optional, for geo-fencing
            $table->timestamps();
            // Add any other zone fields you need (e.g., coordinates, city, etc.)
        });

        // ========================================
        // Users Table
        // ========================================
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // ----------------------------------------
            // Basic Information
            // ----------------------------------------
            $table->string('firstname')->comment("User's first name");
            $table->string('lastname')->comment("User's last name");
            $table->string('phoneNumber')->unique()->comment("User's phone number");
            $table->string('email')->unique()->comment("User's email address");
            $table->string('referralCode')->nullable()->comment("User's unique referral code");
            $table->string('address')->nullable()->comment("User's address");
            $table->decimal('latitude', 10, 7)->nullable()->comment("Latitude of address");
            $table->decimal('longitude', 10, 7)->nullable()->comment("Longitude of address");
            $table->string('profile_picture')->nullable()->comment("Profile image path");
            $table->json('notifications')->nullable()->comment("Notification preferences");

            // ----------------------------------------
            // Driver Specific Fields
            // ----------------------------------------
            $table->string('role')->default('customer');
            $table->boolean('is_available')->default(true)->comment("Driver availability status");
            
            // ----------------------------------------
            // Referral System Fields
            // ----------------------------------------
            $table->string('referral_code')->nullable()->unique();
            $table->string('referred_by')->nullable()->index();
            $table->boolean('referral_rewarded')->default(false);
            $table->decimal('wallet_balance', 10, 2)->default(0);
            $table->integer('point_balance')->default(0);
                    
            // Define the foreign key column FIRST, then add the constraint
            $table->unsignedBigInteger('zone_id')->nullable()->comment('Zone ID reference');
            $table->foreign('zone_id')->references('id')->on('zones')->onDelete('set null');
            
            $table->decimal('rating', 2, 1)->nullable()->comment("Driver rating out of 5");
            $table->integer('total_trips')->default(0)->comment("Total trips completed by driver");
            $table->string('profile_image')->nullable()->comment("Driver profile image path");

            // ----------------------------------------
            // Security & Authentication
            // ----------------------------------------
            $table->string('password')->comment("Hashed password");
            $table->string('password_reset_code')->nullable()->comment("Code for password reset");
            $table->timestamp('password_reset_expires_at')->nullable()->comment("When reset code expires");
            $table->rememberToken();
            $table->boolean('two_factor_enabled')->default(false)->comment("Whether 2FA is enabled");
            $table->string('two_factor_secret')->nullable()->comment("2FA secret key");
            $table->boolean('is_active')->default(true)->comment("Whether account is active");

            // ----------------------------------------
            // Email Verification
            // ----------------------------------------
            $table->string('email_verification_code', 10)->nullable()->comment("Code sent for email verification");
            $table->timestamp('email_verification_sent_at')->nullable()->comment("When verification code was sent");
            $table->timestamp('email_verified_at')->nullable()->comment("When email was verified");
            $table->boolean('is_verified')->default(false)->comment("Whether email is verified");

            // ----------------------------------------
            // Login Tracking
            // ----------------------------------------
            $table->timestamp('last_login_at')->nullable()->comment("Last successful login timestamp");
            $table->string('last_login_ip', 45)->nullable()->comment("IP address of last login");
            $table->integer('login_count')->default(0)->comment("Total number of logins");

            // ----------------------------------------
            // System Fields
            // ----------------------------------------
            $table->timestamps();

            // ----------------------------------------
            // Indexes
            // ----------------------------------------
            $table->index(['email', 'is_verified']);
            $table->index(['phoneNumber', 'is_verified']);
            $table->index('referralCode');
            $table->index('created_at');
            $table->index('last_login_at');
            $table->index('is_active');
            $table->index('is_available');
            $table->index('rating');
            $table->index('zone_id'); // optional, improves join performance
        });

        // ========================================
        // Password Reset Tokens Table
        // ========================================
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // ========================================
        // Sessions Table
        // ========================================
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('zones');
        // 'orders' is not created here; remove it to avoid errors on rollback
        // Schema::dropIfExists('orders'); 
    }
};