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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            
            // ========================================
            // Basic Information
            // ========================================
            $table->string('firstname')->comment('User\'s first name');
            $table->string('lastname')->comment('User\'s last name');
            $table->string('phoneNumber')->unique()->comment('User\'s phone number');
            $table->string('email')->unique()->comment('User\'s email address');
            $table->string('referralCode')->nullable()->comment('User\'s unique referral code');
            
            // ========================================
            // Authentication
            // ========================================
            $table->string('password')->comment('Hashed password');
            $table->rememberToken();
            
            // ========================================
            // Email Verification
            // ========================================
            $table->string('email_verification_code', 10)->nullable()
                  ->comment('Code sent for email verification');
            $table->timestamp('email_verification_sent_at')->nullable()
                  ->comment('When verification code was sent');
            $table->timestamp('email_verified_at')->nullable()
                  ->comment('When email was verified');
            $table->boolean('is_verified')->default(false)
                  ->comment('Whether email is verified');
            
            // ========================================
            // Security Settings
            // ========================================
            $table->boolean('two_factor_enabled')->default(false)
                  ->comment('Whether 2FA is enabled');
            $table->string('two_factor_secret')->nullable()
                  ->comment('2FA secret key');
            $table->boolean('is_active')->default(true)
                  ->comment('Whether account is active');
            
            // ========================================
            // Login Tracking
            // ========================================
            $table->timestamp('last_login_at')->nullable()
                  ->comment('Last successful login timestamp');
            $table->string('last_login_ip', 45)->nullable()
                  ->comment('IP address of last login');
            $table->integer('login_count')->default(0)
                  ->comment('Total number of logins');
            
            // ========================================
            // System Fields
            // ========================================
            $table->timestamps();
            
            // ========================================
            // Indexes for Performance
            // ========================================
            $table->index(['email', 'is_verified']);
            $table->index(['phoneNumber', 'is_verified']);
            $table->index('referralCode');
            $table->index('created_at');
            $table->index('last_login_at');
            $table->index('is_active');
        });

        // Password Reset Tokens Table
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Sessions Table
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
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};