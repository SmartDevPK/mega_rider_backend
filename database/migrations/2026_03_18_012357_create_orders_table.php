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
        Schema::create('orders', function (Blueprint $table) {
            // ========================================
            // Primary Identifiers
            // ========================================
            $table->id();
            $table->string('order_id')->unique()->comment('Custom readable order ID (e.g., ORD-20240315-ABC123)');
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');

            // ========================================
            // Pickup Information
            // ========================================
            $table->string('pickup_address');
            $table->decimal('pickup_latitude', 10, 8);
            $table->decimal('pickup_longitude', 11, 8);
            $table->string('pickup_city')->nullable();
            $table->string('pickup_state')->nullable();
            $table->string('pickup_zip_code')->nullable();
            $table->text('pickup_instructions')->nullable();

            // ========================================
            // Delivery Information
            // ========================================
            $table->string('delivery_address');
            $table->decimal('delivery_latitude', 10, 8);
            $table->decimal('delivery_longitude', 11, 8);
            $table->string('dropoff_city')->nullable();
            $table->string('dropoff_state')->nullable();
            $table->string('dropoff_zip_code')->nullable();
            $table->text('delivery_instructions')->nullable();

            // ========================================
            // Sender Details
            // ========================================
            $table->string('sender_name');
            $table->string('sender_email');
            $table->string('sender_phone');
            $table->boolean('use_my_details')->default(false)->comment('Use logged-in user details as sender');

            // ========================================
            // Receiver Details
            // ========================================
            $table->string('receiver_name');
            $table->string('receiver_email');
            $table->string('receiver_phone');

            // ========================================
            // Package Details
            // ========================================
            $table->string('package_name');
            $table->string('package_image')->nullable()->comment('Path to uploaded package image');
            $table->decimal('package_worth', 10, 2);
            $table->boolean('package_insurance')->default(false);
            $table->decimal('insurance_fee', 10, 2)->nullable();
            $table->decimal('package_weight', 8, 2)->nullable();
            $table->string('package_dimensions')->nullable();
            $table->enum('package_category', [
                'document', 'electronics', 'clothing', 'food',
                'fragile', 'liquid', 'other'
            ])->default('other');

            // ========================================
            // Vehicle Details
            // ========================================
            $table->enum('vehicle_type', ['motorcycle', 'bike', 'van', 'car'])->default('motorcycle');
            $table->string('license_plate')->nullable()->comment('Vehicle license plate number');
            $table->string('make')->nullable()->comment('Vehicle make/brand');
            $table->string('model')->nullable()->comment('Vehicle model');
            $table->integer('year')->nullable()->comment('Vehicle year');
            $table->string('color')->nullable()->comment('Vehicle color');
            $table->string('vin', 17)->nullable()->comment('Vehicle Identification Number');

            // ========================================
            // Order Details
            // ========================================
            $table->text('order_instruction')->nullable();
            $table->integer('travel_time')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->decimal('delivery_fee', 10, 2)->nullable();

            // ========================================
            // Tip Information
            // ========================================
            $table->decimal('tip_amount', 10, 2)->nullable();
            $table->string('tip_method')->nullable();
            $table->timestamp('tip_added_at')->nullable();

            // ========================================
            // Order Status
            // ========================================
            $table->enum('status', [
                'pending', 
                'confirmed', 
                'processing', 
                'driver_assigned',
                'picked_up', 
                'in_transit', 
                'delivered', 
                'completed', 
                'cancelled',
                'failed'
            ])->default('pending');

            // ========================================
            // Payment Information
            // ========================================
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->enum('payment_method', ['wallet', 'paystack', 'card', 'cash', 'bank_transfer'])->nullable();
            $table->string('payment_reference')->nullable()->unique();
            $table->timestamp('date_payment_confirmed')->nullable();

            // ========================================
            // Driver Information (Snapshot at assignment)
            // ========================================
            $table->string('driver_name')->nullable()->comment('Driver name at assignment time');
            $table->string('driver_phone')->nullable()->comment('Driver phone at assignment time');
            $table->string('driver_image')->nullable()->comment('Driver image path at assignment time');
            
            // Driver Assignment Tracking
            $table->timestamp('driver_assigned_at')->nullable()->comment('When driver was assigned to order');
            $table->decimal('driver_rating', 2, 1)->nullable()->comment('Rating given to driver for this trip');
            $table->text('driver_notes')->nullable()->comment('Additional notes about driver or trip');

            // ========================================
            // Tracking Timestamps
            // ========================================
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            // ========================================
            // Emergency & Security
            // ========================================
            $table->boolean('emergency_triggered')->default(false)->comment('Whether emergency SOS was triggered');
            $table->timestamp('emergency_time')->nullable()->comment('When emergency was triggered');
            $table->text('emergency_notes')->nullable()->comment('Notes about emergency situation');

            // ========================================
            // Audit Fields
            // ========================================
            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at for archiving

            // ========================================
            // Indexes for Performance
            // ========================================
            $table->index('order_id');
            $table->index('customer_id');
            $table->index('driver_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('vehicle_type');
            $table->index('license_plate');
            $table->index(['customer_id', 'status']);
            $table->index(['driver_id', 'status']);
            $table->index(['status', 'payment_status']);
            $table->index(['created_at', 'status']);
            $table->index(['pickup_latitude', 'pickup_longitude']);
            $table->index(['delivery_latitude', 'delivery_longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};