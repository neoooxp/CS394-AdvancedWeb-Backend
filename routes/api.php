<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BusController;
use App\Http\Controllers\DriverScheduleController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\StudentGuardianController;
use App\Http\Controllers\TelemetryController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| School Bus Management System (SBMS) - API Routes
|--------------------------------------------------------------------------
|
| All endpoints are prefixed with /api automatically via bootstrap/app.php.
| Protected routes require a valid Sanctum Bearer Token.
|
*/

// -----------------------------------------------------------------------
// 🔐 Domain A: Authentication & Access Control (Public)
// -----------------------------------------------------------------------

Route::prefix('auth')->group(function () {
    Route::middleware('throttle:5,1')->post('/login', [AuthController::class, 'login']);
    Route::middleware('throttle:3,1')->post('/password/email', [PasswordResetController::class, 'sendResetLink']);
    Route::middleware('throttle:3,1')->post('/password/reset', [PasswordResetController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
});

// -----------------------------------------------------------------------
// All routes below require a valid Sanctum Bearer Token
// -----------------------------------------------------------------------

Route::middleware('auth:sanctum')->group(function () {

    // -------------------------------------------------------------------
    // 📊 Domain Telemetry & Health Monitoring
    // -------------------------------------------------------------------
    Route::get('/telemetry/database', [TelemetryController::class, 'getDatabaseTelemetry']);

    // -------------------------------------------------------------------
    // 👥 Domain A (cont.): User Account Management (Admin Protected)
    // -------------------------------------------------------------------
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);

    // -------------------------------------------------------------------
    // 🗃️ Domain B: Student & Guardian Directory
    // -------------------------------------------------------------------
    Route::get('/students', [StudentGuardianController::class, 'index']);
    Route::post('/students', [StudentGuardianController::class, 'store']);
    Route::put('/students/{id}', [StudentGuardianController::class, 'update']);
    Route::delete('/students/{id}', [StudentGuardianController::class, 'destroy']);
    Route::post('/students/assign-guardian', [StudentGuardianController::class, 'assignGuardian']);

    // -------------------------------------------------------------------
    // 🚌 Domain C: Fleet Infrastructure (PostgreSQL)
    // -------------------------------------------------------------------
    Route::get('/buses', [BusController::class, 'index']);
    Route::post('/buses', [BusController::class, 'store']);
    Route::put('/buses/{id}', [BusController::class, 'update']);
    Route::post('/buses/{id}/documents', [BusController::class, 'storeDocument']);

    // -------------------------------------------------------------------
    // 🔧 Domain C (cont.): Maintenance Operations (MongoDB + Hybrid)
    // -------------------------------------------------------------------
    Route::get('/maintenance/pending', [MaintenanceController::class, 'getPendingRequests']);
    Route::get('/maintenance/requests', [MaintenanceController::class, 'getPendingRequests']);
    Route::post('/maintenance/requests', [MaintenanceController::class, 'storeRequest']);
    Route::post('/maintenance/requests/{mongo_id}/resolve', [MaintenanceController::class, 'resolveRequest']);

    // -------------------------------------------------------------------
    // 🗺️ Domain D: Route Logistics & Deployment Schedules
    // -------------------------------------------------------------------
    Route::get('/routes', [RouteController::class, 'index']);
    Route::post('/routes', [RouteController::class, 'store']);
    Route::put('/routes/{id}', [RouteController::class, 'update']);
    Route::delete('/routes/{id}', [RouteController::class, 'destroy']);
    Route::post('/routes/{id}/stops', [RouteController::class, 'manageStops']);

    Route::post('/assignments/bus-route', [AssignmentController::class, 'assignBusToRoute']);
    Route::post('/assignments/check-bus-schedule', [AssignmentController::class, 'checkBusScheduleAvailability']);
    Route::post('/assignments/driver-bus', [AssignmentController::class, 'assignDriverToBus']);

    // -------------------------------------------------------------------
    // 🪪 Domain E: Driver Shifts & Availability
    // -------------------------------------------------------------------
    Route::get('/driver/schedule', [DriverScheduleController::class, 'getSchedule']);
    Route::patch('/driver/availability', [DriverScheduleController::class, 'toggleAvailability']);

    // -------------------------------------------------------------------
    // 📝 Domain F: Real-Time Operations & Attendance
    // -------------------------------------------------------------------
    Route::get('/operations/routes/{id}/manifest', [AttendanceController::class, 'getRouteManifest']);
    Route::post('/operations/attendance', [AttendanceController::class, 'markAttendance']);
    Route::post('/operations/attendance/bulk', [AttendanceController::class, 'bulkMarkAttendance']);
    Route::get('/operations/students/{id}/status', [AttendanceController::class, 'getChildStatus']);
    Route::post('/operations/routes/{id}/reports', [AttendanceController::class, 'generateReport']);

    // -------------------------------------------------------------------
    // 💰 Domain F (cont.): Financial Tracking & Billing
    // -------------------------------------------------------------------
    Route::get('/billing/fee-structures', [BillingController::class, 'getFeeStructures']);
    Route::post('/billing/fee-structures', [BillingController::class, 'createFeeStructure']);
    Route::put('/billing/fee-structures/{id}', [BillingController::class, 'updateFeeStructure']);
    Route::post('/billing/assign-fee', [BillingController::class, 'assignFeeStructure']);
    Route::delete('/billing/unassign-fee/{studentId}', [BillingController::class, 'unassignFeeStructure']);
    Route::get('/billing/invoices', [BillingController::class, 'getInvoices']);
    Route::get('/billing/invoices/{id}', [BillingController::class, 'getInvoice']);
    Route::patch('/billing/invoices/{id}/status', [BillingController::class, 'updateInvoiceStatus']);
    Route::post('/billing/invoices/generate', [BillingController::class, 'generateInvoices']);
    Route::get('/billing/guardians/{id}/ledger', [BillingController::class, 'getLedger']);
    Route::post('/billing/payments', [BillingController::class, 'recordPayment']);
    Route::post('/billing/payments/bulk', [BillingController::class, 'recordBulkPayments']);
});
