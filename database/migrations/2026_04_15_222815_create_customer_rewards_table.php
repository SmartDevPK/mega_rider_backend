<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_rewards', function (Blueprint $table) {
            $table->id();

            // who gets the reward
            $table->unsignedBigInteger('customer_id')->constrained('users')->onDelete('cascade');

            // reward type (streak, referral, bonus, etc.)
            $table->string('type');

            // date reward is based on
            $table->date('reference_date');

            // reward amount
            $table->decimal('amount', 10, 2)->default(0);

            // optional related order
            $table->unsignedBigInteger('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            $table->timestamps();

            // prevent duplicate rewards per day/type
            $table->unique(['customer_id', 'type', 'reference_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_rewards');
    }
};
