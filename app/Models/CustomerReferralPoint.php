<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_referral_points', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->string('month', 7); // YYYY-MM
            $table->integer('monthly_points')->default(0);
            $table->integer('points_used')->default(0);
            $table->integer('points_expired')->default(0);
            $table->decimal('point_value', 10, 2)->default(1.00);

            $table->boolean('is_claimed')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Unique constraint
            $table->unique(['customer_id', 'month'], 'idx_customer_referral_unique');

            // Indexes
            $table->index(['customer_id', 'month'], 'idx_referral_customer_month');
            $table->index('expires_at', 'idx_referral_expires');
            $table->index('is_claimed', 'idx_referral_claimed');
            $table->index(['customer_id', 'expires_at'], 'idx_referral_customer_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_referral_points');
    }
};
