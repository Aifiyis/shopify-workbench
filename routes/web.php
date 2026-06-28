<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\OrderProcessingController;
use App\Http\Controllers\ProductTypeController;
use App\Http\Controllers\SkuMatchProductTypeController;

Route::get('/', function () {
    return Auth::guard('admin')->check() ? redirect('/dashboard') : redirect('/login');
});

Route::middleware('guest:admin')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'login'])->name('login.post');
});

Route::middleware('auth:admin')->group(function () {
    Route::post('/logout', [AdminLoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::resource('sku-product-types', SkuMatchProductTypeController::class)->except('show');
    Route::post('product-types/quick-create', [ProductTypeController::class, 'quickStore'])
        ->name('product-types.quick-store');
    Route::resource('product-types', ProductTypeController::class)->except('show');
    Route::resource('order-processing', OrderProcessingController::class)->except('show');

    Route::prefix('admins')->name('admins.')->group(function () {
        Route::get('/', [App\Http\Controllers\AdminManagementController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\AdminManagementController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\AdminManagementController::class, 'store'])->name('store');
        Route::get('/{admin}/edit', [App\Http\Controllers\AdminManagementController::class, 'edit'])->name('edit');
        Route::put('/{admin}', [App\Http\Controllers\AdminManagementController::class, 'update'])->name('update');
        Route::delete('/{admin}', [App\Http\Controllers\AdminManagementController::class, 'destroy'])->name('destroy');
    });

    Route::get('/export/download/{filename}', [ExportController::class, 'download'])->name('export.download');

    Route::prefix('data-processing')->name('data-processing.')->group(function () {
        Route::get('/', [App\Http\Controllers\DataProcessingController::class, 'index'])->name('index');
        Route::post('/upload', [App\Http\Controllers\DataProcessingController::class, 'upload'])->name('upload');
        Route::get('/upload', function (\Illuminate\Http\Request $request) {
            \Log::warning('Data processing upload URL was opened with GET.', [
                'referer' => $request->headers->get('referer'),
                'previous_url' => url()->previous(),
                'query' => $request->query(),
            ]);

            return redirect()->route('data-processing.index')
                ->with('error', 'The upload URL was opened with GET. Please upload from the Data Processing page using Process File.');
        })->name('upload.redirect');
        Route::get('/{id}/download', [App\Http\Controllers\DataProcessingController::class, 'download'])->name('download');
        Route::delete('/{id}', [App\Http\Controllers\DataProcessingController::class, 'delete'])->name('delete');
    });
});
