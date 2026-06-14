<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create orders table with partition-ready structure
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id')->unique();

            // Use BIGINT for foreign keys (already default in Laravel)
            $table->unsignedBigInteger('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('zone_id')->nullable()->constrained('zones')->nullOnDelete();

            // Use shorter strings where possible
            $table->string('pickup_address', 500);
            $table->decimal('pickup_latitude', 10, 7);
            $table->decimal('pickup_longitude', 10, 7);
            $table->string('pickup_city', 100);

            $table->string('dropoff_address', 500);
            $table->decimal('dropoff_latitude', 10, 7);
            $table->decimal('dropoff_longitude', 10, 7);
            $table->string('dropoff_city', 100);

            // Contact fields with length limits
            $table->string('sender_name', 100);
            $table->string('sender_phone', 20);
            $table->string('sender_email', 255);

            $table->string('receiver_name', 100);
            $table->string('receiver_phone', 20);
            $table->string('receiver_email', 255);

            // Numeric fields optimized for precision
            $table->decimal('distance', 8, 2)->nullable();
            $table->integer('estimated_travel_time')->nullable();

            // Package info
            $table->string('package_name', 200);
            $table->decimal('package_worth', 12, 2);
            $table->decimal('price', 12, 2)->nullable();
            $table->string('item_name', 200)->nullable();
            $table->string('package_image')->nullable();
            $table->decimal('insurance_fee', 10, 2)->default(0);
            $table->boolean('insurance_flag')->default(false);

            // Pricing (use 12,2 for large scale)
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('surge_multiplier', 5, 2)->nullable();
            $table->decimal('surge_fee', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();

            // Status fields (use TINYINT for enum in large scale)
            $table->tinyInteger('status')->default(1)->comment('1:pending,2:assigned,3:picked_up,4:delivered,5:cancelled,6:draft');
            $table->boolean('is_draft')->default(false);
            $table->tinyInteger('step')->nullable()->comment('1:pickup,2:dropoff,3:item,4:review');

            // Timestamps (use integer for 100M+ scale)
            $table->unsignedInteger('date_accepted')->nullable();
            $table->unsignedInteger('date_delivered')->nullable();
            $table->unsignedInteger('date_modified')->nullable();
            $table->unsignedInteger('delivered_at')->nullable();
            $table->unsignedInteger('cancelled_at')->nullable();
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');

            $table->text('cancellation_reason')->nullable();
            $table->string('special_instructions', 500)->nullable();
            $table->json('meta')->nullable();
            $table->boolean('streak_counted')->default(false);

            // =========================================================================
            // MINIMAL BUT EFFECTIVE INDEXES FOR 100M+ ROWS
            // =========================================================================

            // Most critical indexes only
            $table->index(['rider_id', 'status', 'date_modified'], 'idx_orders_rider_query');
            $table->index(['customer_id', 'status', 'created_at'], 'idx_orders_customer_query');
            $table->index('order_id', 'idx_orders_uuid');
            $table->index(['status', 'created_at'], 'idx_orders_status_created');
            $table->index('zone_id', 'idx_orders_zone');

            // Cursor pagination
            $table->index(['rider_id', 'date_modified', 'id'], 'idx_orders_cursor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
