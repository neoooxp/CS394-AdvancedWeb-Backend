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
        Schema::create('buses', function (Blueprint $table) {
            $table->id('bus_id');
            $table->string('bus_number')->unique();
            $table->string('plate_number')->unique();
            $table->integer('capacity');
            $table->string('model')->nullable();
            $table->string('manufacturer')->nullable();
            $table->integer('year')->nullable();
            $table->integer('mileage')->default(0);
            $table->string('availability_status')->default('Available');
            $table->string('depot_location')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buses');
    }
};
