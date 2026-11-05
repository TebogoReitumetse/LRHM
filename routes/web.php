<?php

use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\LeaveCategoryController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaveApplicationController;
use App\Http\Controllers\LeaveRegisterController;
use App\Http\Controllers\LeaveReviewController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // ---- Phase 1: Leave applications (employee) ----
    Route::get('/leave/calculate', [LeaveApplicationController::class, 'calculate'])->name('leave.calculate');
    Route::resource('leave', LeaveApplicationController::class)->parameters(['leave' => 'leave']);
    Route::post('/leave/{leave}/submit', [LeaveApplicationController::class, 'submit'])->name('leave.submit');
    Route::get('/leave/{leave}/print', [LeaveApplicationController::class, 'print'])->name('leave.print');
    Route::get('/leave/{leave}/documents/{document}', [LeaveApplicationController::class, 'document'])->name('leave.document');
    Route::delete('/leave/{leave}/documents/{document}', [LeaveApplicationController::class, 'destroyDocument'])->name('leave.document.destroy');

    // ---- Phase 1: Review workflow ----
    Route::get('/approvals', [LeaveReviewController::class, 'index'])->name('approvals.index');
    Route::post('/leave/{leave}/start-review', [LeaveReviewController::class, 'startReview'])->name('leave.start-review');
    Route::post('/leave/{leave}/return', [LeaveReviewController::class, 'return'])->name('leave.return');
    Route::post('/leave/{leave}/forward', [LeaveReviewController::class, 'forward'])->name('leave.forward');
    Route::post('/leave/{leave}/decide', [LeaveReviewController::class, 'decide'])->name('leave.decide');

    // ---- Phase 1: Register, reports, absences (management) ----
    Route::middleware('can:view-leave-register')->group(function () {
        Route::get('/register', [LeaveRegisterController::class, 'index'])->name('register.index');
        Route::get('/register/export/{format}', [LeaveRegisterController::class, 'export'])->whereIn('format', ['csv', 'pdf', 'print'])->name('register.export');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/run', [ReportController::class, 'run'])->name('reports.run');
    });

    Route::middleware('can:manage-absences')->group(function () {
        Route::resource('absences', AbsenceController::class)->except(['destroy']);
        Route::get('/absences/{absence}/document', [AbsenceController::class, 'document'])->name('absences.document');
    });

    // ---- Phase 2: Contracts ----
    Route::get('/my-contract', [ContractController::class, 'mine'])->name('contracts.mine');
    Route::get('/contracts/{contract}/documents/{document}', [ContractController::class, 'document'])->name('contracts.document');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])->whereNumber('contract')->name('contracts.show');
    Route::middleware('can:manage-contracts')->group(function () {
        Route::get('/contracts/dashboard', [ContractController::class, 'dashboard'])->name('contracts.dashboard');
        Route::get('/contracts/export/{format}', [ContractController::class, 'export'])->whereIn('format', ['csv', 'pdf', 'print'])->name('contracts.export');
        Route::resource('contracts', ContractController::class)->except(['show', 'destroy']);
        Route::post('/contracts/{contract}/documents', [ContractController::class, 'upload'])->name('contracts.upload');
        Route::get('/contracts/{contract}/renew', [ContractController::class, 'renewForm'])->name('contracts.renew');
        Route::post('/contracts/{contract}/renew', [ContractController::class, 'renew']);
        Route::post('/contracts/{contract}/end', [ContractController::class, 'end'])->name('contracts.end');
        Route::post('/contracts/run-monitor', [ContractController::class, 'runMonitor'])->name('contracts.run-monitor');
    });

    // ---- Administration ----
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:manage-users')->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
        });
        Route::middleware('can:manage-settings')->group(function () {
            Route::resource('leave-categories', LeaveCategoryController::class)->only(['index', 'store', 'update']);
            Route::resource('holidays', HolidayController::class)->only(['index', 'store', 'destroy']);
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
            Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
            Route::get('backups/{name}', [BackupController::class, 'download'])->name('backups.download');
        });
        Route::get('audit', [AuditLogController::class, 'index'])->middleware('can:view-audit')->name('audit.index');
    });
});
