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
        Schema::create('user_reports', function (Blueprint $table) {
            $table->id();

            // ----------------------------------------
            // Who is reporting
            // ----------------------------------------
            $table->foreignId('reporter_id')
                  ->constrained('users')        // Must exist in users table
                  ->cascadeOnDelete()           // Delete reports if user is deleted
                  ->comment('ID of the user who is reporting');

            // ----------------------------------------
            // Who is being reported
            // ----------------------------------------
            $table->foreignId('reported_id')
                  ->constrained('users')        // Must exist in users table
                  ->cascadeOnDelete()           // Delete reports if reported user is deleted
                  ->comment('ID of the user being reported');

            // ----------------------------------------
            // Reason for reporting (optional)
            // ----------------------------------------
            $table->foreignId('reason_id')
                  ->nullable()                  // Optional
                  ->constrained('report_reasons')
                  ->nullOnDelete()              // Set to NULL if reason deleted
                  ->comment('Reason for reporting (optional)');

            // ----------------------------------------
            // Optional comment
            // ----------------------------------------
            $table->text('comment')
                  ->nullable()
                  ->comment('Optional comment explaining the report');

            // ----------------------------------------
            // Timestamps
            // ----------------------------------------
            $table->timestamps();

            // ----------------------------------------
            // Indexes
            // ----------------------------------------
            $table->index('reporter_id');
            $table->index('reported_id');
            $table->index('reason_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_reports');
    }
};
