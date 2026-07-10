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
        Schema::create('driver_bus_assignments', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->foreignId('driver_id')->constrained('drivers', 'id')->onDelete('cascade');
            $table->foreignId('bus_id')->constrained('buses', 'bus_id')->onDelete('cascade');
            $table->date('assigned_date');
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_bus_assignments');
    }
};
