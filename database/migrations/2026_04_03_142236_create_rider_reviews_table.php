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
      Schema::create('rider_reviews', function (Blueprint $table) {
    $table->id();

    // Order (UUID reference)
    $table->uuid('order_id');
    $table->foreign('order_id')
          ->references('order_id')
          ->on('orders')
          ->cascadeOnDelete();

    // Relationships
    $table->foreignId('rider_id')
          ->constrained('users')
          ->cascadeOnDelete();

    $table->foreignId('customer_id')
          ->constrained('users')
          ->cascadeOnDelete();

    // Ratings (1–5)
    $table->unsignedTinyInteger('performance_rating');
    $table->unsignedTinyInteger('speed_rating');
    $table->unsignedTinyInteger('handling_rating');

    // Calculated average
    $table->decimal('average_rating', 3, 2);

    // Review content
    $table->text('review_content')->nullable();

    // Prevent duplicate reviews
    $table->unique(['order_id', 'customer_id']);

    // Optional indexes (performance boost)
    $table->index('rider_id');
    $table->index('customer_id');

    $table->timestamps();
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
