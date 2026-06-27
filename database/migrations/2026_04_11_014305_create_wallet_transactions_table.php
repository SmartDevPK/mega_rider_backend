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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('customer_id')->constrained('users')->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('type'); // credit | debit
            $table->string('purpose'); // wallet_topup, order_payment, refund

            $table->timestamps();

            // index should be INSIDE here
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
