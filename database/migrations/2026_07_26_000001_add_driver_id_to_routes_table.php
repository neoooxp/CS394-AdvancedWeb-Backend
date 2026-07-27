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
        if (!Schema::hasColumn('routes', 'driver_id')) {
            Schema::table('routes', function (Blueprint $table) {
                $table->unsignedBigInteger('driver_id')->nullable()->after('estimated_duration');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('routes', 'driver_id')) {
            Schema::table('routes', function (Blueprint $table) {
                $table->dropColumn('driver_id');
            });
        }
    }
};
