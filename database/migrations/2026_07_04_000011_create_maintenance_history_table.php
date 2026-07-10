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
        Schema::create('maintenance_history', function (Blueprint $table) {
            $table->id('repair_id');
            $table->foreignId('bus_id')->constrained('buses', 'bus_id')->onDelete('cascade');
            $table->string('maintenance_id');
            $table->text('repair_details');
            $table->decimal('repair_cost', 10, 2);
            $table->date('repair_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_history');
    }
};
