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
        // First, create the table WITHOUT foreign key
        Schema::create('customer_referral_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('month');          // e.g., '2026-04'
            $table->integer('monthly_points')->default(0);
            $table->timestamps();
            $table->unique(['customer_id', 'month']);
        });

        // Then, add ONLY the foreign key constraint (NOT the column again)
        Schema::table('customer_referral_points', function (Blueprint $table) {
            $table->foreign('customer_id')
                  ->references('id')
                  ->on('customers')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_referral_points', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        Schema::dropIfExists('customer_referral_points');
    }
};