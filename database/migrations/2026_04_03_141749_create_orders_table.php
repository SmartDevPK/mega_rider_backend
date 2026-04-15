<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id')->unique();

            // Relationships
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            // $table->foreignId('order_type_id')->nullable()->constrained('order_types')->nullOnDelete();?
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();

            // Pickup Information
            $table->string('pickup_address');
            $table->decimal('pickup_latitude', 10, 7);
            $table->decimal('pickup_longitude', 10, 7);
            $table->string('pickup_city');

            // Dropoff Information
            $table->string('dropoff_address');
            $table->decimal('dropoff_latitude', 10, 7);
            $table->decimal('dropoff_longitude', 10, 7);
            $table->string('dropoff_city');

            // Sender Information
            $table->string('sender_name');
            $table->string('sender_phone');
            $table->string('sender_email');

            // Receiver Information
            $table->string('receiver_name');
            $table->string('receiver_phone');
            $table->string('receiver_email');
            
            // Order Details
            $table->decimal('distance', 8, 2)->nullable(); // in km
            $table->integer('estimated_travel_time')->nullable(); 

            // Package Information
            $table->string('package_name');
            $table->decimal('package_worth', 10, 2);
            $table->decimal('price', 10, 2)->nullable();
            $table->string('item_name')->nullable();
            $table->string('package_image')->nullable();
            $table->decimal('insurance_fee', 10, 2)->default(0);
            $table->boolean('insurance_flag')->default(false);

            // Instructions & Notes
             $table->string('special_instructions', 500)->nullable();
            //  $table->timestamp('date_modified')->nullable();

            // Pricing & Surge (added for order type updates)
            $table->timestamp('date_modified')->nullable();
            // $table->decimal('delivery_fee', 10, 2)->nullable();
            // $table->decimal('surge_multiplier', 5, 2)->nullable();
            // $table->decimal('surge_fee', 10, 2)->nullable();
            // $table->decimal('total_amount', 10, 2)->nullable();

            // Order Status
            $table->enum('status', ['pending', 'assigned', 'picked_up', 'delivered', 'cancelled'])
                  ->default('pending');

            // Cancellation fields
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Indexes
            $table->index('customer_id');
            $table->index('rider_id');
            $table->index('status');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};