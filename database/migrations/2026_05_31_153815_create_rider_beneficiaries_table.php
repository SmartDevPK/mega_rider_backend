<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rider_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rider_id')->constrained('riders')->onDelete('cascade');
            $table->string('account_name');
            $table->string('account_number');
            $table->string('bank_code');
            $table->string('bank_name');
            $table->string('beneficiary_name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // Indexes
            $table->index('rider_id');
            $table->index(['rider_id', 'is_default']);
            $table->unique(['rider_id', 'account_number', 'bank_code'], 'unique_beneficiary');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rider_beneficiaries');
    }
};
