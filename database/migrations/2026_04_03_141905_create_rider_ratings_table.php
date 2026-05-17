<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riders', function (Blueprint $table) {

            // -------------------------
            // Primary Key
            // -------------------------
            $table->id();

            // -------------------------
            // Authentication
            // -------------------------
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            
            // -------------------------
            // Identity verification
            
            $table->string('identity_hash')->unique();

            // -------------------------
            // Personal details
            // -------------------------
            $table->string('first_name')->nullable();
            $table->string('surname')->nullable();
            $table->string('phone')->unique()->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('house_address')->nullable();
            $table->string('nin')->nullable(); // removed unique (safer)

            // -------------------------
            // Vehicle details
            // -------------------------
            $table->string('vehicle_color')->nullable();
            $table->string('vehicle_number_plate')->nullable();
            $table->string('vehicle_license_number')->nullable();

            // -------------------------
            // Guarantors
            // -------------------------
            $table->string('first_guarantor_name')->nullable();
            $table->string('first_guarantor_phone')->nullable();
            $table->string('second_guarantor_name')->nullable();
            $table->string('second_guarantor_phone')->nullable();

            // -------------------------
            // Next of kin
            // -------------------------
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_phone')->nullable();

            // -------------------------
            // Work history
            // -------------------------
            $table->string('previous_work')->nullable();
            $table->unsignedInteger('previous_work_years')->nullable();

            // -------------------------
            // File paths
            // -------------------------
            $table->string('rider_license_file_path')->nullable();
            $table->string('rider_license_photo_path')->nullable();
            $table->string('utility_bill_path')->nullable();
            $table->string('rider_photo_path')->nullable();

            // -------------------------
            // User acceptance (self-submitted)
            // -------------------------
            $table->boolean('address_verified')->default(false);
            $table->boolean('guarantors_accepted')->default(false);
            $table->boolean('rider_license_accepted')->default(false);

            // -------------------------
            // Admin approvals
            // -------------------------
            $table->boolean('nin_approved')->default(false);
            $table->boolean('guarantor_approved')->default(false);
            $table->boolean('rider_license_approved')->default(false);

            // -------------------------
            // Status
            // -------------------------
            $table->enum('status', [
                'pending_approval',
                'approved',
                'suspended'
            ])->default('pending_approval');

            // -------------------------
            // Referral
            // -------------------------
            $table->string('referral_code')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riders');
    }
};
