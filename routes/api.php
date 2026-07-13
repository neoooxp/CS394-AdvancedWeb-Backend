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
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/password/email', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/password/reset', [PasswordResetController::class, 'resetPassword']);

    // Requires authentication to logout
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
});

// -----------------------------------------------------------------------
// All routes below require a valid Sanctum Bearer Token
// -----------------------------------------------------------------------

Route::middleware('auth:sanctum')->group(function () {

    // -------------------------------------------------------------------
    // 👥 Domain A (cont.): User Account Management (Admin Protected)
    // -------------------------------------------------------------------
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);

    // -------------------------------------------------------------------
    // 🗃️ Domain B: Student & Guardian Directory
    // -------------------------------------------------------------------
    Route::get('/students', [StudentGuardianController::class, 'index']);
    Route::post('/students', [StudentGuardianController::class, 'store']);
    Route::put('/students/{id}', [StudentGuardianController::class, 'update']);
    Route::post('/students/assign-guardian', [StudentGuardianController::class, 'assignGuardian']);

    // -------------------------------------------------------------------
    // 🚌 Domain C: Fleet Infrastructure (PostgreSQL)
    // -------------------------------------------------------------------
    Route::get('/buses', [BusController::class, 'index']);
    Route::post('/buses', [BusController::class, 'store']);
    Route::post('/buses/{id}/documents', [BusController::class, 'storeDocument']);

    // -------------------------------------------------------------------
    // 🔧 Domain C (cont.): Maintenance Operations (MongoDB + Hybrid)
    // -------------------------------------------------------------------
    Route::get('/maintenance/pending', [MaintenanceController::class, 'getPendingRequests']);
    Route::post('/maintenance/requests', [MaintenanceController::class, 'storeRequest']);
    Route::post('/maintenance/requests/{mongo_id}/resolve', [MaintenanceController::class, 'resolveRequest']);

    // -------------------------------------------------------------------
    // 🗺️ Domain D: Route Logistics & Deployment Schedules
    // -------------------------------------------------------------------
    Route::get('/routes', [RouteController::class, 'index']);
    Route::post('/routes', [RouteController::class, 'store']);
    Route::post('/routes/{id}/stops', [RouteController::class, 'manageStops']);

    Route::post('/assignments/bus-route', [AssignmentController::class, 'assignBusToRoute']);
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
    Route::get('/operations/students/{id}/status', [AttendanceController::class, 'getChildStatus']);
    Route::post('/operations/routes/{id}/reports', [AttendanceController::class, 'generateReport']);

    // -------------------------------------------------------------------
    // 💰 Domain F (cont.): Financial Tracking & Billing
    // -------------------------------------------------------------------
    Route::post('/billing/fee-structures', [BillingController::class, 'createFeeStructure']);
    Route::post('/billing/invoices/generate', [BillingController::class, 'generateInvoices']);
    Route::get('/billing/guardians/{id}/ledger', [BillingController::class, 'getLedger']);
    Route::post('/billing/payments', [BillingController::class, 'recordPayment']);
});
