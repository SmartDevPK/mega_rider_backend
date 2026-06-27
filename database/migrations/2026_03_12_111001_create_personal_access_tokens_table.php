<?php

declare(strict_types=1);

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the personal_access_tokens table for Sanctum API authentication.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            // Primary key
            $table->id();

            // Polymorphic relationship (creates tokenable_id and tokenable_type)
            $table->morphs('tokenable');

            // Token information
            $table->text('name');                      // Token name/description
            $table->string('token', 64)->unique();     // Hashed token value
            $table->text('abilities')->nullable();      // Token abilities/scopes

            // Token lifecycle tracking
            $table->timestamp('last_used_at')->nullable();  // Last API request time
            $table->timestamp('expires_at')->nullable();    // Token expiration time
            $table->timestamps();                           // created_at, updated_at

            // Performance indexes
            $table->index('expires_at');                                    // For cleaning expired tokens
            $table->index('created_at');                                    // For token age queries
            $table->index(['tokenable_type', 'last_used_at'], 'idx_tokenable_last_used'); // Multi-column optimization
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
