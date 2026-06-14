<?php

declare(strict_types=1);

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
        Schema::create('rider_reviews', function (Blueprint $table): void {
            $table->id();

            // Order reference (UUID)
            // $table->uuid('order_id');
            $table->unsignedBigInteger('order_id')
                ->references('order_id')
                ->on('orders')
                ->cascadeOnDelete();

            // Relationships
            $table->unsignedBigInteger('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->constrained('customers')
                ->cascadeOnDelete();

            // Ratings (1-5)
            $table->unsignedTinyInteger('performance_rating');
            $table->unsignedTinyInteger('speed_rating');
            $table->unsignedTinyInteger('handling_rating');
            $table->decimal('average_rating', 3, 2);

            // Review content
            $table->text('review_content')->nullable();

            $table->timestamps();

            // Constraints
            $table->unique(['order_id', 'customer_id'], 'idx_rider_reviews_unique');

            // Indexes
            $table->index('rider_id', 'idx_rider_reviews_rider');
            $table->index('customer_id', 'idx_rider_reviews_customer');
            $table->index(['rider_id', 'created_at'], 'idx_rider_reviews_rider_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rider_reviews');
    }
};
