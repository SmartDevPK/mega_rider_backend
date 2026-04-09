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
        Schema::create('rider_ratings', function (Blueprint $table) {
            $table->id();

            // Relationship (only once)
            $table->foreignId('rider_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Ensure one record per rider
            $table->unique('rider_id');

            // Counters
            $table->unsignedInteger('total_ratings')->default(0);

            $table->unsignedBigInteger('total_performance')->default(0);
            $table->unsignedBigInteger('total_speed')->default(0);
            $table->unsignedBigInteger('total_handling')->default(0);

            // Averages
            $table->decimal('avg_performance', 3, 2)->default(0);
            $table->decimal('avg_speed', 3, 2)->default(0);
            $table->decimal('avg_handling', 3, 2)->default(0);

            // Overall rating
            $table->decimal('overall_rating', 3, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rider_ratings');
    }
};
