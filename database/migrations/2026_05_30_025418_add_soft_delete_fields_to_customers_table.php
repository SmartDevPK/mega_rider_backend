<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeleteFieldsToCustomersTable extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(false)->after('password');
            $table->boolean('is_email_verified')->default(true)->after('is_deleted');
            // $table->timestamp('deleted_at')->nullable()->after('date_modified'); // Optional
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['is_deleted', 'is_email_verified']);
        });
    }
}