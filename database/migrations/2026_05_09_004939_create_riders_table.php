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
        Schema::create('riders', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | PERSONAL INFORMATION
            |--------------------------------------------------------------------------
            */
            $table->string('first_name');
            $table->string('last_name');

            $table->string('phone_number', 20)->unique();
            $table->string('email')->unique();

            $table->enum('gender', [
                'male',
                'female',
                'other'
            ]);

            $table->text('address');

            $table->string('nin', 11)->unique();

            /*
            |--------------------------------------------------------------------------
            | FILE UPLOADS
            |--------------------------------------------------------------------------
            */
            $table->string('image_path')->nullable();
            $table->string('proof_of_address_path')->nullable();
            $table->string('driver_license_path')->nullable();

            /*
            |--------------------------------------------------------------------------
            | VEHICLE INFORMATION
            |--------------------------------------------------------------------------
            */
            $table->enum('vehicle_type', [
                'car',
                'bike',
                'bicycle',
                'tricycle'
            ]);

            $table->string('vehicle_color', 50);

            $table->string('vehicle_plate_number')->unique();

            $table->string('driver_license_number')->unique();

            /*
            |--------------------------------------------------------------------------
            | GUARANTOR INFORMATION
            |--------------------------------------------------------------------------
            */
            $table->string('guarantor_name');

            $table->string('guarantor_phone', 20);

            $table->string('guarantor_relationship', 50);

            $table->string('guarantor_address')->nullable();

            $table->string('guarantor_occupation')->nullable();

            /*
            |--------------------------------------------------------------------------
            | NEXT OF KIN
            |--------------------------------------------------------------------------
            */
            $table->string('nok_name');

            $table->string('nok_phone', 20);

            $table->string('nok_relationship', 50);

            $table->string('nok_address')->nullable();

            /*
            |--------------------------------------------------------------------------
            | WORK HISTORY
            |--------------------------------------------------------------------------
            */
            $table->string('previous_place_of_work')->nullable();

            $table->integer('years_of_work')->default(0);

            /*
            |--------------------------------------------------------------------------
            | LOGIN & DEVICE TRACKING
            |--------------------------------------------------------------------------
            */
            $table->timestamp('last_login_at')->nullable();

            $table->string('last_login_ip')->nullable();

            $table->string('fcm_token')->nullable();

            /*
            |--------------------------------------------------------------------------
            | PASSWORD RESET
            |--------------------------------------------------------------------------
            */
            $table->string('password_reset_token')->nullable();

            $table->timestamp('password_reset_token_expires_at')->nullable();

            $table->integer('password_reset_attempts')->default(0);

            /*
            |--------------------------------------------------------------------------
            | OTP & EMAIL VERIFICATION
            |--------------------------------------------------------------------------
            */
            $table->string('otp_code')->nullable();

            $table->timestamp('otp_expires_at')->nullable();

            $table->timestamp('otp_verified_at')->nullable();

            $table->timestamp('email_verified_at')->nullable();

            $table->integer('otp_attempts')->default(0);

            $table->timestamp('otp_last_attempt_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | APPROVAL SYSTEM
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'pending',
                'approved',
                'rejected'
            ])->default('pending');

            $table->text('rejection_reason')->nullable();

            $table->timestamp('approved_at')->nullable();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('admins')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | AUTHENTICATION
            |--------------------------------------------------------------------------
            */
            $table->string('password')->nullable();

            $table->timestamp('password_set_at')->nullable();

            $table->rememberToken();

            /*
            |--------------------------------------------------------------------------
            | TIMESTAMPS
            |--------------------------------------------------------------------------
            */
            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | INDEXES
            |--------------------------------------------------------------------------
            */
            $table->index([
                'status',
                'created_at'
            ]);

            $table->index('phone_number');

            $table->index('email');

            $table->index('vehicle_plate_number');
            $table->index('vehicle_name');
            $table->index('otp_code');

            $table->index('otp_expires_at');

            $table->index('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riders');
    }
};