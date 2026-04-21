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
        Schema::create('customer_referral_points', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('customer_id');
        $table->string('month');          // e.g., '2026-04'
        $table->integer('monthly_points')->default(0);
        $table->timestamps();

        $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');
        $table->unique(['customer_id', 'month']);
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_referral_points');
    }
};
