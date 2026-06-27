<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_reports', function (Blueprint $table): void {
            $table->id();

            // Polymorphic reporter (customer or rider)
            $table->morphs('reporter');

            // Polymorphic reported (customer or rider)
            $table->morphs('reported');

            $table->unsignedBigInteger('reason_id')
                ->nullable()
                ->constrained('report_reasons')
                ->nullOnDelete();

            $table->text('comment')->nullable();
            $table->string('custom_reason', 200)->nullable();

            $table->enum('status', ['pending', 'reviewing', 'resolved', 'dismissed', 'escalated'])
                ->default('pending');

            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            $table->unsignedBigInteger('resolved_by')
                ->nullable()
                ->constrained('admins')
                ->nullOnDelete();

            $table->text('admin_notes')->nullable();
            $table->enum('action_taken', ['none', 'warning', 'temporary_ban', 'permanent_ban', 'restricted'])
                ->default('none');

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            // Indexes for polymorphic queries
            $table->index(['reporter_type', 'reporter_id', 'created_at'], 'idx_reports_reporter');
            $table->index(['reported_type', 'reported_id', 'status'], 'idx_reports_reported_status');
            $table->index(['status', 'priority', 'created_at'], 'idx_reports_status_priority');
            $table->index('created_at', 'idx_reports_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reports');
    }
};
