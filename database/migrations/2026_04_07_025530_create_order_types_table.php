<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_types', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('price_per_km', 10, 2)->default(0);
            $table->decimal('base_distance', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Add order_type_id to orders
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('order_type_id')->nullable()->constrained('order_types')->nullOnDelete();
            $table->timestamp('date_modified')->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('surge_multiplier', 5, 2)->default(0);
            $table->decimal('surge_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['order_type_id']);
            $table->dropColumn(['order_type_id','date_modified','delivery_fee','surge_multiplier','surge_fee','total_amount']);
        });

        Schema::dropIfExists('order_types');
    }
};
