<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetAllocationController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'storeRegistration'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/dashboard/diagnostics', [HomeController::class, 'dashboardDiagnostics'])->name('dashboard.diagnostics');
    Route::get('/dashboard/audit-logs/export', [HomeController::class, 'exportAuditLogs'])->name('dashboard.audit-logs.export');
    Route::post('/schools', [HomeController::class, 'storeSchool'])->name('schools.store');
    Route::get('/procurement', [HomeController::class, 'procurement'])->name('procurement');
    Route::get('/procurement/suppliers', [HomeController::class, 'suppliers'])->name('suppliers');
    Route::post('/procurement/suppliers', [HomeController::class, 'storeSupplier'])->name('suppliers.store');
    Route::put('/procurement/suppliers/{supplier}', [HomeController::class, 'updateSupplier'])->name('suppliers.update');
    Route::get('/procurement/create', [HomeController::class, 'createProcurement'])->name('procurement.create');
    Route::post('/procurement', [HomeController::class, 'storeProcurement'])->name('procurement.store');
    Route::get('/procurement/{procurementRequest}/edit', [HomeController::class, 'editProcurement'])->name('procurement.edit');
    Route::put('/procurement/{procurementRequest}', [HomeController::class, 'updateProcurement'])->name('procurement.update');
    Route::get('/procurement/{procurementRequest}/print', [HomeController::class, 'printProcurement'])->name('procurement.print');
    Route::get('/procurement/{procurementRequest}/documents', [HomeController::class, 'procurementDocuments'])->name('procurement.documents');
    Route::post('/procurement/{procurementRequest}/documents', [HomeController::class, 'storeProcurementDocument'])->name('procurement.documents.store');
    Route::get('/procurement/{procurementRequest}/documents/{procurementDocument}/print', [HomeController::class, 'printProcurementDocument'])->name('procurement.documents.print');
    Route::get('/procurement/{procurementRequest}/delivery-reconciliation', [HomeController::class, 'printDeliveryReconciliation'])->name('procurement.delivery-reconciliation');
    Route::get('/liquidation', [HomeController::class, 'liquidation'])->name('liquidation');
    Route::post('/liquidation', [HomeController::class, 'storeLiquidation'])->name('liquidation.store');
    Route::get('/liquidation/{liquidationReport}/print', [HomeController::class, 'printOrs'])->name('liquidation.print');
    Route::patch('/liquidation/{liquidationReport}/status', [HomeController::class, 'updateLiquidationStatus'])->name('liquidation.status');
    Route::get('/budget', [BudgetController::class, 'index'])->name('budget');
    Route::get('/budget/balance', [BudgetController::class, 'balance'])->name('budget.balance');
    Route::prefix('budget/allocation')->name('budget.allocation')->group(function () {
        Route::get('/', [BudgetAllocationController::class, 'index']);
        Route::get('/create', [BudgetAllocationController::class, 'create'])->name('.create');
        Route::post('/', [BudgetAllocationController::class, 'store'])->name('.store');
        Route::post('/close', [BudgetAllocationController::class, 'close'])->name('.close');
        Route::get('/reports/{type}', [BudgetAllocationController::class, 'report'])->name('.report');
        Route::get('/{budgetAllocation}', [BudgetAllocationController::class, 'show'])->name('.show')->whereNumber('budgetAllocation');
        Route::get('/{budgetAllocation}/edit', [BudgetAllocationController::class, 'edit'])->name('.edit')->whereNumber('budgetAllocation');
        Route::put('/{budgetAllocation}', [BudgetAllocationController::class, 'update'])->name('.update')->whereNumber('budgetAllocation');
        Route::delete('/{budgetAllocation}', [BudgetAllocationController::class, 'destroy'])->name('.destroy')->whereNumber('budgetAllocation');
    });
    Route::get('/accounting', [FinanceController::class, 'accounting'])->name('accounting');
    Route::get('/accounting/chart-of-accounts', [ChartOfAccountController::class, 'index'])->name('chart-of-accounts');
    Route::post('/accounting/chart-of-accounts', [ChartOfAccountController::class, 'store'])->name('chart-of-accounts.store');
    Route::put('/accounting/chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'update'])->name('chart-of-accounts.update');
    Route::delete('/accounting/chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'destroy'])->name('chart-of-accounts.destroy');
    Route::patch('/accounting/{liquidationReport}', [FinanceController::class, 'review'])->name('accounting.review');
    Route::post('/accounting/{liquidationReport}/dv', [FinanceController::class, 'createDv'])->name('accounting.dv.store');
    Route::get('/accounting/{liquidationReport}/dv-print', [FinanceController::class, 'printDv'])->name('accounting.dv.print');
    Route::get('/cash', [FinanceController::class, 'cash'])->name('cash');
    Route::post('/cash/{liquidationReport}/pay', [FinanceController::class, 'pay'])->name('cash.pay');
    Route::get('/google-drive', [HomeController::class, 'googleDrive'])->name('google-drive');
    Route::post('/google-drive/settings', [HomeController::class, 'updateGoogleDriveSettings'])->name('google-drive.settings');
    Route::get('/reports', [HomeController::class, 'reports'])->name('reports');
    Route::get('/user-management', [HomeController::class, 'userManagement'])->name('user-management');
    Route::get('/subscriptions', [HomeController::class, 'subscriptions'])->name('subscriptions');
    Route::get('/school-settings', [HomeController::class, 'schoolSettings'])->name('school-settings');
    Route::post('/school-settings/agency', [HomeController::class, 'updateAgencySettings'])->name('school-settings.agency');
    Route::post('/school-settings/school', [HomeController::class, 'updateSchoolDetails'])->name('school-settings.school');
    Route::post('/school-settings/schools/{school}/approve', [HomeController::class, 'approveSchoolRegistration'])->name('school-settings.school.approve');
    Route::post('/school-settings/staff', [HomeController::class, 'updateSchoolStaff'])->name('school-settings.staff');
    Route::post('/school-settings/staff/add', [HomeController::class, 'addSchoolStaff'])->name('school-settings.staff.add');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::post('/generate', [HomeController::class, 'generate'])->name('generate');
});
