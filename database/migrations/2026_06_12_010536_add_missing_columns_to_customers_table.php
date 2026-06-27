<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToCustomersTable extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('customers', 'email_verification_code')) {
                $table->string('email_verification_code', 8)->nullable();
            }
            if (!Schema::hasColumn('customers', 'email_verification_sent_at')) {
                $table->timestamp('email_verification_sent_at')->nullable();
            }
            if (!Schema::hasColumn('customers', 'password_reset_code')) {
                $table->string('password_reset_code', 6)->nullable();
            }
            if (!Schema::hasColumn('customers', 'password_reset_expires_at')) {
                $table->timestamp('password_reset_expires_at')->nullable();
            }
            if (!Schema::hasColumn('customers', 'deactivation_reason')) {
                $table->text('deactivation_reason')->nullable();
            }
            if (!Schema::hasColumn('customers', 'deactivated_at')) {
                $table->timestamp('deactivated_at')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'email_verification_code',
                'email_verification_sent_at',
                'password_reset_code',
                'password_reset_expires_at',
                'deactivation_reason',
                'deactivated_at'
            ]);
        });
    }
}
