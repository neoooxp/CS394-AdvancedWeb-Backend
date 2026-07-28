<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_attendance', function (Blueprint $table) {
            $table->index('student_id');
            $table->index('recorded_by');
            $table->index(['student_id', 'date']);
            $table->index(['date', 'status']);
        });

        Schema::table('students_stop', function (Blueprint $table) {
            $table->index('student_id');
            $table->index('route_id');
            $table->index(['route_id', 'stop_order']);
        });

        Schema::table('student_guardians', function (Blueprint $table) {
            $table->index('guardian_id');
            $table->index('student_id');
        });

        Schema::table('attendance_reports', function (Blueprint $table) {
            $table->index('route_id');
        });

        Schema::table('driver_bus_assignments', function (Blueprint $table) {
            $table->index('driver_id');
            $table->index('bus_id');
            $table->index(['assigned_date', 'status']);
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->index('driver_id');
        });

        Schema::table('bus_routes', function (Blueprint $table) {
            $table->index('bus_id');
            $table->index('route_id');
        });

        Schema::table('bus_documents', function (Blueprint $table) {
            $table->index('bus_id');
        });

        Schema::table('driver_schedules', function (Blueprint $table) {
            $table->index('driver_id');
            $table->index('is_available');
        });

        Schema::table('maintenance_history', function (Blueprint $table) {
            $table->index('bus_id');
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->index('student_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index('guardian_id');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('invoice_id');
        });

        Schema::table('password_resets', function (Blueprint $table) {
            $table->index('reset_token');
            $table->index(['user_id', 'reset_token', 'expires_at']);
        });

        Schema::table('buses', function (Blueprint $table) {
            $table->index('availability_status');
        });
    }

    public function down(): void
    {
        Schema::table('daily_attendance', function (Blueprint $table) {
            $table->dropIndex(['student_id']);
            $table->dropIndex(['recorded_by']);
            $table->dropIndex(['student_id', 'date']);
            $table->dropIndex(['date', 'status']);
        });

        Schema::table('students_stop', function (Blueprint $table) {
            $table->dropIndex(['student_id']);
            $table->dropIndex(['route_id']);
            $table->dropIndex(['route_id', 'stop_order']);
        });

        Schema::table('driver_bus_assignments', function (Blueprint $table) {
            $table->dropIndex(['driver_id']);
            $table->dropIndex(['bus_id']);
            $table->dropIndex(['assigned_date', 'status']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['guardian_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
        });

        Schema::table('password_resets', function (Blueprint $table) {
            $table->dropIndex(['reset_token']);
            $table->dropIndex(['user_id', 'reset_token', 'expires_at']);
        });

        Schema::table('student_guardians', function (Blueprint $table) {
            $table->dropIndex(['guardian_id']);
            $table->dropIndex(['student_id']);
        });

        Schema::table('attendance_reports', function (Blueprint $table) {
            $table->dropIndex(['route_id']);
        });

        Schema::table('routes', function (Blueprint $table) {
            $table->dropIndex(['driver_id']);
        });

        Schema::table('bus_routes', function (Blueprint $table) {
            $table->dropIndex(['bus_id']);
            $table->dropIndex(['route_id']);
        });

        Schema::table('bus_documents', function (Blueprint $table) {
            $table->dropIndex(['bus_id']);
        });

        Schema::table('driver_schedules', function (Blueprint $table) {
            $table->dropIndex(['driver_id']);
            $table->dropIndex(['is_available']);
        });

        Schema::table('maintenance_history', function (Blueprint $table) {
            $table->dropIndex(['bus_id']);
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropIndex(['student_id']);
        });

        Schema::table('buses', function (Blueprint $table) {
            $table->dropIndex(['availability_status']);
        });
    }
};
