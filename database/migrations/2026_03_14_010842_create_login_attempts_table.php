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
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')
                ->nullable()
                ->constrained()
                ->onDelete('cascade');

            $table->string('email', 255);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->boolean('success')->default(false);
            $table->boolean('is_lockout')->default(false);

            $table->timestamp('attempted_at');
            $table->timestamps();

            // Performance indexes
            $table->index(['email', 'attempted_at']);
            $table->index(['user_id', 'attempted_at']);
            $table->index('attempted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
