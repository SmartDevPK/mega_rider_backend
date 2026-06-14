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
        Schema::create('rider_authentications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rider_id');
            $table->string('code', 6);
            $table->integer('attempts')->default(0);
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('rider_id')
                  ->references('id')
                  ->on('riders')
                  ->onDelete('cascade');
                  
            // Index for faster lookups
            $table->index(['rider_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rider_authentications');
    }
};