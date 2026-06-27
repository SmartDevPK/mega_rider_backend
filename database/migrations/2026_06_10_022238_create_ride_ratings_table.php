<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First check if rides table exists
        if (!Schema::hasTable('rides')) {
            // Create rides table first
            Schema::create('rides', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
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
                $table->timestamps();
            });
        }

        // Now create ride_ratings table
        Schema::create('ride_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_id')->constrained('rides')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->tinyInteger('customer_rating')->unsigned()->comment('Customer rating for rider (1-5)');
            $table->tinyInteger('rider_rating')->unsigned()->comment('Rider rating for customer (1-5)');
            $table->text('customer_comment')->nullable();
            $table->text('rider_comment')->nullable();
            $table->json('customer_ratings_details')->nullable();
            $table->json('rider_ratings_details')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->boolean('is_reported')->default(false);
            $table->text('report_reason')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['ride_id']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['rider_id', 'created_at']);
            $table->index(['customer_rating', 'created_at']);
            $table->index(['rider_rating', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_ratings');
        Schema::dropIfExists('rides');
    }
};
