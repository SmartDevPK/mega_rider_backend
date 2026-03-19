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
            // Primary Identifier
            $table->id();
            $table->string('order_id')->unique()->comment('Custom readable order ID (e.g., ORD-20240315-ABC123)');
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');

            // Pickup Information
            $table->string('pickup_address');
            $table->decimal('pickup_latitude', 10, 8)->comment('Latitude coordinates for pickup');
            $table->decimal('pickup_longitude', 11, 8)->comment('Longitude coordinates for pickup');
            $table->string('pickup_city')->nullable();
            $table->string('pickup_state')->nullable();
            $table->string('pickup_zip_code')->nullable();
            $table->text('pickup_instructions')->nullable();

            // Delivery Information
            $table->string('delivery_address');
            $table->decimal('delivery_latitude', 10, 8)->comment('Latitude coordinates for delivery');
            $table->decimal('delivery_longitude', 11, 8)->comment('Longitude coordinates for delivery');
            $table->string('dropoff_city')->nullable();
            $table->string('dropoff_state')->nullable();
            $table->string('dropoff_zip_code')->nullable();
            $table->text('delivery_instructions')->nullable();

            // Sender Details
            $table->string('sender_name');
            $table->string('sender_email');
            $table->string('sender_phone');
            $table->boolean('use_my_details')->default(false)->comment('Use logged-in user details as sender');

            // Receiver Details
            $table->string('receiver_name');
            $table->string('receiver_email');
            $table->string('receiver_phone');

            // Package Details
            $table->string('package_name');
            $table->string('package_image')->nullable()->comment('Path to uploaded package image');
            $table->decimal('package_worth', 10, 2)->comment('Monetary value of package');
            $table->boolean('package_insurance')->default(false)->comment('Whether insurance is selected');
            $table->decimal('insurance_fee', 10, 2)->nullable()->comment('Calculated insurance fee');
            $table->decimal('package_weight', 8, 2)->nullable()->comment('Weight in kg');
            $table->string('package_dimensions')->nullable()->comment('LxWxH in cm');
            $table->enum('package_category', [
                'document', 'electronics', 'clothing', 'food',
                'fragile', 'liquid', 'other'
            ])->default('other');

            // Order Details
            $table->enum('order_type', ['express', 'standard', 'scheduled'])->default('standard');
            $table->enum('vehicle_type', ['motorcycle', 'bike', 'van', 'car'])->default('motorcycle');
            $table->text('order_instruction')->nullable();
            $table->integer('travel_time')->nullable()->comment('Estimated travel time in minutes');
            $table->decimal('distance_km', 8, 2)->nullable()->comment('Total distance in kilometers');
            $table->decimal('delivery_fee', 10, 2)->nullable()->comment('Calculated delivery fee');

            // Tip Information
            $table->decimal('tip_amount', 10, 2)->nullable();
            $table->string('tip_method')->nullable();
            $table->timestamp('tip_added_at')->nullable();

            // Order Status
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
            ])->default('pending')->comment('Current order status');

            // Payment Information
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->enum('payment_method', ['wallet', 'paystack', 'card', 'cash', 'bank_transfer'])->nullable();
            $table->string('payment_reference')->nullable()->unique()->comment('Payment gateway reference');
            $table->timestamp('date_payment_confirmed')->nullable();

            // Tracking Information
            $table->timestamp('driver_assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            // Audit Fields
            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at for archiving

            // Indexes
            $table->index('order_id');
            $table->index('customer_id');
            $table->index('driver_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('order_type');
            $table->index('created_at');
            $table->index(['customer_id', 'status']);
            $table->index(['driver_id', 'status']);
            $table->index(['status', 'payment_status']);
            $table->index(['created_at', 'status']);
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
