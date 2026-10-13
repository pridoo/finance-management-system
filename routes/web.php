<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\MSWDO\ApprovalController;
use App\Http\Controllers\MSWDO\AssistanceRequestController;
use App\Http\Controllers\MSWDO\BudgetController;
use App\Http\Controllers\MSWDO\ClientController;
use App\Http\Controllers\MSWDO\DashboardController;
use App\Http\Controllers\MSWDO\ReportController;
use Illuminate\Support\Facades\Route;

// Redirect root to login
Route::redirect('/', '/login')->name('home');

// Polled by open pages to notice `php artisan down` while someone is mid-task.
// Answers 204 normally; maintenance mode turns it into a 503 like every route.
Route::get('/ping', fn () => response()->noContent())->name('ping');

// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Account administration. Separate from the MSWDO prefix because the admin
// manages logins only and holds no welfare-programme access. The MSWDO officer
// reaches these same pages to run staff logins, bounded by the checks in the
// controllers: no touching administrator accounts, no granting the admin role.
//
// 'notviewonly' is here too, so an officer marked view-only can read the
// account list but not change it — the same rule their MSWDO modules follow.
Route::middleware(['auth', 'active', 'admin', 'notviewonly'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->except(['show']);
    Route::patch('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    Route::resource('roles', RoleController::class)->except(['show']);
});

// MSWDO protected routes. 'active' ends the session of an account disabled
// mid-session; 'notviewonly' blocks writes for read-only accounts; and each
// group carries the module permission the admin assigns per user.
Route::middleware(['auth', 'active', 'notviewonly'])->prefix('mswdo')->name('mswdo.')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('module:dashboard')->name('dashboard');

    // Beneficiaries
    Route::get('/beneficiaries/{client}/intake-form', [ClientController::class, 'intakePdf'])
        ->name('beneficiaries.intake-pdf')
        ->middleware('module:beneficiaries');
    Route::resource('beneficiaries', ClientController::class)
        ->parameters(['beneficiaries' => 'client'])
        ->middleware('module:beneficiaries');

    // Assistance Requests
    Route::resource('requests', AssistanceRequestController::class)
        ->parameters(['requests' => 'assistanceRequest'])
        ->middleware('module:requests');

    // Approvals
    Route::resource('approvals', ApprovalController::class)
        ->parameters(['approvals' => 'approval'])
        ->only(['index', 'show', 'store', 'update'])
        ->middleware('module:approvals');

    // Budget
    Route::resource('budget', BudgetController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy'])
        ->middleware('module:budget');

    // Named portions of a budget — "College", "TUPAD", "School Supplies".
    // Nested under the budget they belong to so the parent is always in hand.
    Route::middleware('module:budget')->group(function () {
        // The year's whole fund, which the per-type allocations draw from.
        Route::post('/budget-fund', [BudgetController::class, 'storeFund'])
            ->name('budget.fund.store');

        Route::post('/budget/{budget}/sub-allocations', [BudgetController::class, 'storeSubAllocation'])
            ->name('budget.sub-allocations.store');
        Route::delete('/budget/{budget}/sub-allocations/{subAllocation}', [BudgetController::class, 'destroySubAllocation'])
            ->name('budget.sub-allocations.destroy');
    });

    // Reports
    Route::middleware('module:reports')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf'])->name('reports.export-pdf');
    });
});
